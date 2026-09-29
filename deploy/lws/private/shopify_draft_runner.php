<?php
declare(strict_types=1);

require_once __DIR__.'/shopify_admin_client.php';
require_once __DIR__.'/shopify_proof_verifier.php';

final class ShopifyDraftRunner {
    public static function run(PDO $db,ShopifyAdminClient $shop,string $taskId,string $fingerprint,array $draft): array {
        $db->beginTransaction();
        try {
            $q=$db->prepare('SELECT draft_id,status,shopify_product_id FROM vp_product_drafts WHERE fingerprint=? FOR UPDATE');
            $q->execute([$fingerprint]);
            $row=$q->fetch(PDO::FETCH_ASSOC);
            if($row && in_array($row['status'],['created'],true) && $row['shopify_product_id']){
                $db->commit();
                return ['duplicate'=>true,'product_id'=>$row['shopify_product_id']];
            }
            if(!$row){
                $q=$db->prepare("INSERT INTO vp_product_drafts(task_id,source_reference,fingerprint,title,description,price,payload_json,status)
                  VALUES(?,?,?,?,?,?,?,'validated')");
                $q->execute([
                  $taskId,$draft['source_reference'],$fingerprint,$draft['title'],$draft['description'],$draft['price'],
                  json_encode($draft,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)
                ]);
                $draftId=(int)$db->lastInsertId();
            } else {
                $draftId=(int)$row['draft_id'];
            }
            $db->commit();

            // External call happens outside the DB transaction.
            $created=$shop->createDraft($draft);
            $actual=$shop->readProduct((string)$created['id']);
            $proof=ShopifyProofVerifier::verifyDraft(['title'=>$draft['title']],$actual);
            if(!$proof['verified']) throw new RuntimeException('Shopify proof mismatch');

            $q=$db->prepare("UPDATE vp_product_drafts SET status='created',shopify_product_id=?,shopify_handle=?,
              proof_verified=1,verified_at=CURRENT_TIMESTAMP,last_error=NULL WHERE draft_id=?");
            $q->execute([(string)$actual['id'],(string)($actual['handle']??''),$draftId]);
            $q=$db->prepare("UPDATE vp_product_intake SET processing_status='draft_created' WHERE task_id=?");
            $q->execute([$taskId]);
            return ['duplicate'=>false,'product_id'=>$actual['id'],'handle'=>$actual['handle']??'','verified'=>true];
        } catch(Throwable $e) {
            if($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }
}

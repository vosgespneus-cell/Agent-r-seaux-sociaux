<?php
declare(strict_types=1);

final class OpenAISupervisorClient
{
    public function __construct(private array $config) {
        if (empty($config['api_key']) || str_starts_with((string)$config['api_key'], 'REPLACE_')) {
            throw new RuntimeException('openai_api_key_missing');
        }
    }

    public function route(string $systemPrompt, string $eventSummary, array $schema): array
    {
        $payload = [
            'model' => $this->config['model'] ?? 'gpt-5.6-luna',
            'input' => [
                ['role'=>'system','content'=>$systemPrompt],
                ['role'=>'user','content'=>$eventSummary],
            ],
            'max_output_tokens' => (int)($this->config['max_output_tokens'] ?? 600),
            'text' => [
                'format' => [
                    'type'=>'json_schema',
                    'name'=>'vp_supervisor_decision',
                    'strict'=>true,
                    'schema'=>$schema,
                ],
            ],
        ];

        $ch=curl_init((string)($this->config['endpoint'] ?? 'https://api.openai.com/v1/responses'));
        curl_setopt_array($ch,[
            CURLOPT_POST=>true, CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>false,
            CURLOPT_CONNECTTIMEOUT=>10, CURLOPT_TIMEOUT=>(int)($this->config['timeout_seconds'] ?? 30),
            CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.$this->config['api_key']],
            CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),
        ]);
        $raw=curl_exec($ch);
        $status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);
        $err=curl_error($ch);
        curl_close($ch);
        if ($raw===false) throw new RuntimeException('openai_transport_error:'.$err);
        if ($status===429 || $status>=500) throw new RuntimeException('openai_temporary_error');
        if ($status<200 || $status>=300) throw new DomainException('openai_request_rejected');

        $data=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
        if (($data['status'] ?? null)==='incomplete') throw new RuntimeException('openai_incomplete');

        foreach (($data['output'] ?? []) as $item) {
            if (($item['type'] ?? '') !== 'message') continue;
            foreach (($item['content'] ?? []) as $content) {
                if (($content['type'] ?? '') === 'refusal') throw new DomainException('openai_refusal');
                if (($content['type'] ?? '') === 'output_text') {
                    $decision=json_decode((string)$content['text'],true,512,JSON_THROW_ON_ERROR);
                    if (!is_array($decision)) throw new RuntimeException('openai_invalid_output');
                    return $decision;
                }
            }
        }
        throw new RuntimeException('openai_output_missing');
    }
}

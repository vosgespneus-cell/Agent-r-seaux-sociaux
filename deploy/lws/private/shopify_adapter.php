<?php
declare(strict_types=1);

/**
 * Shopify adapter contract v1.
 * No token is stored in this repository. Runtime secrets belong in vp_config.php.
 */
final class ShopifyAdapter {
    private array $config;
    public function __construct(array $config) { $this->config=$config; }

    public function mode(): string { return (string)($this->config['mode'] ?? 'read_only'); }

    public function assertAllowed(string $operation): void {
        $allowed=$this->config['capabilities'][$operation] ?? false;
        if ($allowed !== true) {
            throw new RuntimeException('Shopify operation blocked by policy: '.$operation);
        }
    }

    public function normalizeProduct(array $product): array {
        return [
            'external_id'=>(string)($product['id'] ?? ''),
            'title'=>(string)($product['title'] ?? ''),
            'handle'=>(string)($product['handle'] ?? ''),
            'status'=>(string)($product['status'] ?? ''),
            'vendor'=>(string)($product['vendor'] ?? ''),
            'inventory'=>(int)($product['totalInventory'] ?? 0),
            'updated_at'=>(string)($product['updatedAt'] ?? ''),
        ];
    }
}

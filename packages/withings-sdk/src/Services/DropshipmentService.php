<?php

namespace Filipac\Withings\Services;

class DropshipmentService extends BaseService
{
    /**
     * Create a new dropshipment order
     */
    public function createOrder(array $orderData): array
    {
        return $this->client->post('/v2/dropshipment', array_merge([
            'action' => 'createorder',
        ], $orderData));
    }

    /**
     * Update an existing dropshipment order
     */
    public function updateOrder(string $orderId, array $updateData): array
    {
        return $this->client->post('/v2/dropshipment', array_merge([
            'action' => 'updateorder',
            'order_id' => $orderId,
        ], $updateData));
    }

    /**
     * Get order status and details
     */
    public function getOrder(string $orderId): array
    {
        return $this->client->post('/v2/dropshipment', [
            'action' => 'getorder',
            'order_id' => $orderId,
        ]);
    }

    /**
     * List orders with optional filters
     */
    public function listOrders(array $filters = []): array
    {
        return $this->client->post('/v2/dropshipment', array_merge([
            'action' => 'listorders',
        ], $filters));
    }

    /**
     * Cancel an order
     */
    public function cancelOrder(string $orderId): array
    {
        return $this->client->post('/v2/dropshipment', [
            'action' => 'cancelorder',
            'order_id' => $orderId,
        ]);
    }

    /**
     * Get available products for dropshipment
     */
    public function getProducts(): array
    {
        return $this->client->post('/v2/dropshipment', [
            'action' => 'getproducts',
        ]);
    }

    /**
     * Get shipping methods and costs
     */
    public function getShippingMethods(array $shippingData): array
    {
        return $this->client->post('/v2/dropshipment', array_merge([
            'action' => 'getshippingmethods',
        ], $shippingData));
    }
}

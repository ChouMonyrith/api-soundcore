<?php

namespace App\Services;
use Illuminate\Support\Facades\Http;
use KHQR\BakongKHQR;
use KHQR\Helpers\KHQRData;
use KHQR\Models\MerchantInfo;
use Exception;
use Illuminate\Support\Facades\Log;

class BakongKHQRService
{
    protected $config;

    public function __construct()
    {
        $this->config = config('bakong');
    }

    /**
     * Generate a KHQR code payload for an order
     */
    public function generateMerchantQR(float $amount): array
{
    try {
        $info = new MerchantInfo(
            bakongAccountID: $this->config['account_id'],
            merchantName: $this->config['merchant_name'],
            merchantCity: $this->config['merchant_city'],
            merchantID: $this->config['merchant_id'],
            acquiringBank: $this->config['acquiring_bank']
        );

        $info->amount = $amount;
        $info->currency = KHQRData::CURRENCY_USD;

        // Generate KHQR response (returns KHQRResponse object)
        $response = BakongKHQR::generateMerchant($info);

        // The KHQRResponse object has ->status and ->data
        if ($response->status['code'] !== 0) {
            throw new Exception($response->data['message']);
        }

        return [
            'payload' => $response->data['qr'],
            'md5'     => $response->data['md5']
        ];

    } catch (Exception $e) {
        throw new Exception('KHQR Generation failed: ' . $e->getMessage());
    }
}


    /**
     * Check payment status via Bakong API (if supported)
     */
    public function checkPaymentStatus(string $md5)
    {
        $url = 'https://api-bakong.nbc.gov.kh/v1/check_transaction_by_md5';
        $response = Http::withToken(config('bakong.api_token'))
            ->acceptJson()
            ->post($url, ['md5' => $md5]);

        Log::info('ResponseCode'. $response['responseCode']);

        return $response->json();
    }

}

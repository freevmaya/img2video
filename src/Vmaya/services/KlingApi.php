<?

namespace App\Services\API;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class KlingApi extends BaseKlingApi
{
    protected function extendOptions() {
        return [
        	'callback_url'=> KL_HOOK_URL
        ];
    }
}
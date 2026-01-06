<?php
namespace App\Services\API;

interface APIInterface
{
    public function Generate($type, $images, $prompt, $model_name = null);
}
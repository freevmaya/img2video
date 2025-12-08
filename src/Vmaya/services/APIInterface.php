<?php
namespace App\Services\API;

interface APIInterface
{
    public function generateImage($prompt);
    public function generateImageFromImage($imagePath, $prompt);
    public function generateVideoFromImage($imagePath, $prompt);
    public function getStatus($jobId);
}
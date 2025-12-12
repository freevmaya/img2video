<?php
namespace App\Services\API;

interface APIInterface
{
    public function generateImage($prompt, $options=[]);
    public function generateImageFromImage($imagePath, $prompt, $options=[]);
    public function generateVideoFromImage($imagePath, $prompt, $options=[]);
}
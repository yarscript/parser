<?php


namespace App\Repositories;


interface DxRepositoryInterface
{
    public function get(string $dxCode, ?string $sfId = null): array;
}

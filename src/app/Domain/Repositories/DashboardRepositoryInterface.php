<?php

namespace App\Domain\Repositories;

interface DashboardRepositoryInterface
{
    /**
     * @return array<string, mixed>
     */
    public function ownerSummary(): array;
}

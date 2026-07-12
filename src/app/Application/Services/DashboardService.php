<?php

namespace App\Application\Services;

use App\Domain\Repositories\DashboardRepositoryInterface;

class DashboardService
{
    public function __construct(
        private readonly DashboardRepositoryInterface $dashboard,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function ownerSummary(): array
    {
        return $this->dashboard->ownerSummary();
    }
}

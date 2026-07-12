<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Domain\Repositories\UserRepositoryInterface;
use App\Domain\Repositories\OrderRepositoryInterface;
use App\Domain\Repositories\AddressRepositoryInterface;
use App\Domain\Repositories\PaymentRepositoryInterface;
use App\Domain\Repositories\ProductRepositoryInterface;
use App\Domain\Repositories\CategoryRepositoryInterface;
use App\Domain\Repositories\DeliveryRepositoryInterface;
use App\Domain\Repositories\DashboardRepositoryInterface;
use App\Infrastructure\Repositories\EloquentUserRepository;
use App\Infrastructure\Repositories\EloquentOrderRepository;
use App\Domain\Repositories\StockMovementRepositoryInterface;
use App\Infrastructure\Repositories\EloquentAddressRepository;
use App\Infrastructure\Repositories\EloquentPaymentRepository;
use App\Infrastructure\Repositories\EloquentProductRepository;
use App\Infrastructure\Repositories\EloquentCategoryRepository;
use App\Infrastructure\Repositories\EloquentDeliveryRepository;
use App\Infrastructure\Repositories\EloquentDashboardRepository;
use App\Infrastructure\Repositories\EloquentStockMovementRepository;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, EloquentUserRepository::class);
        $this->app->bind(CategoryRepositoryInterface::class, EloquentCategoryRepository::class);
        $this->app->bind(ProductRepositoryInterface::class, EloquentProductRepository::class);
        $this->app->bind(AddressRepositoryInterface::class, EloquentAddressRepository::class);
        $this->app->bind(OrderRepositoryInterface::class, EloquentOrderRepository::class);
        $this->app->bind(PaymentRepositoryInterface::class, EloquentPaymentRepository::class);
        $this->app->bind(DeliveryRepositoryInterface::class, EloquentDeliveryRepository::class);
        $this->app->bind(StockMovementRepositoryInterface::class, EloquentStockMovementRepository::class);
        $this->app->bind(DashboardRepositoryInterface::class, EloquentDashboardRepository::class);
    }

    public function boot(): void
    {
              \Illuminate\Support\Facades\URL::forceScheme('https');
    }
}

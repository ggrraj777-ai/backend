<?php

namespace Modules\UserManagement\Service;


use App\Service\BaseService;
use Illuminate\Database\Eloquent\Model;
use Modules\UserManagement\Repository\DriverDetailRepositoryInterface;
use Modules\UserManagement\Service\Interface\DriverDetailServiceInterface;

class DriverDetailService extends BaseService implements DriverDetailServiceInterface
{
    protected $driverDetailRepository;

    public function __construct(DriverDetailRepositoryInterface $driverDetailRepository)
    {
        parent::__construct($driverDetailRepository);
        $this->driverDetailRepository = $driverDetailRepository;
    }

    public function updateAvailability(array $data = [])
    {
        $driver = $this->driverDetailRepository->findOneBy(criteria: ['user_id' => $data['user_id']]);
        $criteria = [];
        $criteria = match ($data['trip_type']) {
            'ride_request' => ['ride_count' => --$driver->ride_count],
            'parcel' => ['parcel_count' => --$driver->parcel_count],
            default => ['ride_count' => --$driver->ride_count],
        };
        $criteria['availability_status'] = 'available';
        $this->driverDetailRepository->updatedBy(criteria: ['user_id' => $data['user_id']], data: $criteria);
    }
}

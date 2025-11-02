<?php

namespace Modules\UserManagement\Service\Interface;

use App\Service\BaseServiceInterface;

interface DriverDetailServiceInterface extends BaseServiceInterface
{
    public function updateAvailability(array $data = []);
}

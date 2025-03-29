<?php

namespace App\Service;

use App\Repository\UserRepository;

class UserService
{

    public function __construct(
        private UserRepository $userRepository,
    )
    {
    }


    public function findTotalOfPresenters()
    {
        return $this->userRepository->countPresenters();
    }

}
<?php

declare(strict_types=1);

$this->respond('/?', function ($request, $response, $app) {
    echo 'yup';
});

$this->respond('/testing/?', function ($request, $response, $app) {
    echo 'yup';
});

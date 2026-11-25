<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesPerPage;

abstract class Controller
{
    use ResolvesPerPage;
}

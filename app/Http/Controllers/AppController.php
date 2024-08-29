<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Enums\RoleEnum;
use App\Models\Customer;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;

class AppController extends Controller
{
    public function index()
    {
        return view('admin.index');
    }

    public function orders()
    {
        return view('admin.orders.index');
    }

    public function products()
    {
        return view('admin.products.index');
    }

    public function customers()
    {
        return view('admin.customers.index');
    }

    public function users()
    {
        return view('admin.users.index');
    }
}

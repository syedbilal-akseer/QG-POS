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
        $pageTitle = "Dashboard";
        return view('admin.index', compact('pageTitle'));
    }

    public function orders()
    {
        $pageTitle = "Dashboard";
        return view('admin.orders.index', compact('pageTitle'));
    }

    public function products()
    {
        $pageTitle = "Dashboard";
        return view('admin.products.index', compact('pageTitle'));
    }

    public function customers()
    {
        $pageTitle = "Dashboard";
        return view('admin.customers.index', compact('pageTitle'));
    }

    public function users()
    {
        $pageTitle = "Dashboard";
        return view('admin.users.index', compact('pageTitle'));
    }

    public function monthlyTourPlans()
    {
        $pageTitle = "Monthly Tour Plans";
        return view('crm.monthly-tour-plans', compact('pageTitle'));
    }

    public function visits()
    {
        $pageTitle = "Manage Visits";
        return view('crm.manage-visit', compact('pageTitle'));
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class OrderRecieptsController extends Controller
{
    public function index()
    {
        $pageTitle = 'Reciepts';  // Set the page title
        return view('frontend.reciepts', compact('pageTitle'));  // Pass it to the view
    }
}
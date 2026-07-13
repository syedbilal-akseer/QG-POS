<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class OrderReciepts extends Controller
{
    public function index(){
        $pageTitle = 'Reciepts';
        return view('frontend.reciepts', compact('pageTitle'));
    }
}
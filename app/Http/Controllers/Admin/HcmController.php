<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class HcmController extends Controller
{
    /**
     * Module 1: Employee Hiring - Dashboard
     */
    public function dashboard()
    {
        $pageTitle = 'HCM Dashboard';
        return view('admin.hcm.dashboard', compact('pageTitle'));
    }

    /**
     * Module 1: Job Requisition
     */
    public function requisition()
    {
        $pageTitle = 'Job Requisitions';
        return view('admin.hcm.hiring.requisition', compact('pageTitle'));
    }

    /**
     * Module 1: Candidate Management
     */
    public function candidates()
    {
        $pageTitle = 'Candidate Pool';
        return view('admin.hcm.hiring.candidates', compact('pageTitle'));
    }

    /**
     * Module 1: Onboarding
     */
    public function onboarding()
    {
        $pageTitle = 'Onboarding';
        return view('admin.hcm.hiring.onboarding', compact('pageTitle'));
    }

    /**
     * Module 2: Performance Dashboard
     */
    public function performance()
    {
        $pageTitle = 'KPI Dashboard';
        return view('admin.hcm.performance.dashboard', compact('pageTitle'));
    }

    /**
     * Module 2: Goal Setting
     */
    public function goals()
    {
        $pageTitle = 'Goal Setting';
        return view('admin.hcm.performance.goals', compact('pageTitle'));
    }

    /**
     * Module 2: Appraisals
     */
    public function appraisals()
    {
        $pageTitle = 'Appraisals';
        return view('admin.hcm.performance.appraisals', compact('pageTitle'));
    }

    /**
     * Integration & Admin
     */
    public function integration()
    {
        $pageTitle = 'Integration';
        return view('admin.hcm.integration', compact('pageTitle'));
    }
}

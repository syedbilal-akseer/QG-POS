<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

trait OracleNlsSession
{
    /**
     * Set the Oracle session NLS parameters to ensure consistent date and number parsing.
     * This is crucial for EBS views that depend on implicit conversions.
     */
    protected function setOracleNlsSession()
    {
        try {
            DB::connection('oracle')->statement("ALTER SESSION SET NLS_LANGUAGE = 'AMERICAN'");
            DB::connection('oracle')->statement("ALTER SESSION SET NLS_TERRITORY = 'AMERICA'");
            DB::connection('oracle')->statement("ALTER SESSION SET NLS_DATE_FORMAT = 'DD-MON-YYYY HH24:MI:SS'");
            DB::connection('oracle')->statement("ALTER SESSION SET NLS_NUMERIC_CHARACTERS = '.,'");
            
            if (isset($this)) {
                if (method_exists($this, 'info')) {
                    $this->info('Oracle NLS session parameters set successfully.');
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to set Oracle NLS session parameters: ' . $e->getMessage());
            if (isset($this) && method_exists($this, 'error')) {
                $this->error('Failed to set Oracle NLS session parameters: ' . $e->getMessage());
            }
        }
    }
}
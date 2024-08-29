<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OracleCustomer extends Model
{
    use HasFactory;

    /**
     * The connection name for the model.
     *
     * @var string
     */
    protected $connection = 'oracle';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'apps.qg_pos_customer_master';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'customer_id';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'ou_id',
        'ou_name',
        'customer_name',
        'customer_number',
        'customer_id',
        'customer_site_id',
        'salesperson',
        'city',
        'area',
        'address1',
        'contact_number',
        'email_address',
        'overall_credit_limit',
        'credit_days',
        'nic',
        'ntn',
        'sales_tax_registration_num',
        'category_code',
        'creation_date',
        'payment_terms_id',
        'payment_terms_name',
    ];
}

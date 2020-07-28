<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Controllers\Shop;

use App\Events\Invoice\InvoiceWasCreated;
use App\Factory\InvoiceFactory;
use App\Http\Controllers\BaseController;
use App\Http\Requests\Invoice\StoreInvoiceRequest;
use App\Models\Client;
use App\Models\CompanyToken;
use App\Models\Invoice;
use App\Models\InvoiceInvitation;
use App\Repositories\InvoiceRepository;
use App\Transformers\InvoiceTransformer;
use App\Utils\Ninja;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Request;

class InvoiceController extends BaseController
{
    use MakesHash;
    
    protected $entity_type = Invoice::class;

    protected $entity_transformer = InvoiceTransformer::class;

    /**
     * @var InvoiceRepository
     */
    protected $invoice_repo;

    /**
     * InvoiceController constructor.
     *
     * @param      \App\Repositories\InvoiceRepository  $invoice_repo  The invoice repo
     */
    public function __construct(InvoiceRepository $invoice_repo)
    {
        parent::__construct();

        $this->invoice_repo = $invoice_repo;
    }

    public function show(string $invitation_key)
    {
        $company = Company::where('company_key', $request->header('X-API-COMPANY-KEY'))->first();

        if(!$company->enable_shop_api)
            return response()->json(['message' => 'Shop is disabled', 'errors' => []],403);

        $invitation = InvoiceInvitation::with(['invoice'])
                                        ->where('company_id', $company->id)
                                        ->where('key',$invitation_key)
                                        ->firstOrFail();

        return $this->itemResponse($invitation->invoice);
    }


    public function store(StoreInvoiceRequest $request)
    {
                
        $company = Company::where('company_key', $request->header('X-API-COMPANY-KEY'))->first();

        if(!$company->enable_shop_api)
            return response()->json(['message' => 'Shop is disabled', 'errors' => []],403);

        app('queue')->createPayloadUsing(function () use ($company) {
            return ['db' => $company->db];
        });
        
        $client = Client::find($request->input('client_id'));

        $invoice = $this->invoice_repo->save($request->all(), InvoiceFactory::create($company_id, $company->owner()->id));

        event(new InvoiceWasCreated($invoice, $company, Ninja::eventVars()));

        $invoice = $invoice->service()->triggeredActions($request)->save();
        
        return $this->itemResponse($invoice);
    }

}

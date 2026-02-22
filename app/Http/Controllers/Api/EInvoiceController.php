<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreInvoiceRequest;
use App\Models\CompanyProfile;
use App\Models\Invoice;
use App\Services\Accounting\EInvoicingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class EInvoiceController extends Controller
{
    public function __construct(private readonly EInvoicingService $eInvoicingService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('e_invoices.view'), 403);

        return response()->json($this->eInvoicingService->list((int) $request->integer('per_page', 15)));
    }

    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        $invoice = $this->eInvoicingService->create($request->validated(), (int) $request->user()->id);

        return response()->json($invoice, 201);
    }

    public function show(Invoice $invoice): JsonResponse
    {
        abort_unless(auth()->user()?->can('e_invoices.view'), 403);

        return response()->json($this->eInvoicingService->find($invoice->id));
    }

    public function issue(Request $request, Invoice $invoice): JsonResponse
    {
        abort_unless($request->user()?->can('e_invoices.issue'), 403);

        return response()->json($this->eInvoicingService->issue($invoice));
    }

    public function cancel(Request $request, Invoice $invoice): JsonResponse
    {
        abort_unless($request->user()?->can('e_invoices.cancel'), 403);

        return response()->json($this->eInvoicingService->cancel($invoice));
    }

    public function transmit(Request $request, Invoice $invoice): JsonResponse
    {
        abort_unless($request->user()?->can('e_invoices.transmit'), 403);

        return response()->json($this->eInvoicingService->transmit($invoice), 201);
    }

    public function print(Request $request, Invoice $invoice): Response
    {
        abort_unless($request->user()?->can('e_invoices.view'), 403);

        $invoice = $this->eInvoicingService->find($invoice->id);
        $company = CompanyProfile::query()->first();

        $pdf = Pdf::loadView('reports.e-invoice', [
            'company' => $company,
            'invoice' => $invoice,
        ]);

        return $pdf->stream('e-invoice-'.$invoice->invoice_number.'.pdf');
    }
}

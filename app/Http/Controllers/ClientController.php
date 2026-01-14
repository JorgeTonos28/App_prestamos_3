<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\LoanLedgerEntry;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $query = Client::query();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('national_id', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $clients = $query->latest()->get();

        return Inertia::render('Clients/Index', [
            'clients' => $clients,
            'filters' => $request->only(['search'])
        ]);
    }

    public function create()
    {
        return Inertia::render('Clients/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'national_id' => 'required|unique:clients',
            'first_name' => 'required',
            'last_name' => 'required',
            'phone' => 'nullable',
            'email' => 'nullable|email',
            'address' => 'nullable',
            'notes' => 'nullable',
        ]);

        $client = Client::create($validated);

        if ($request->wantsJson()) {
            return response()->json($client);
        }

        return redirect()->route('clients.index');
    }

    public function show(Client $client)
    {
        $client->load(['loans' => function($q) {
            $q->latest();
        }]);

        // Calculate Total Interest Paid (Profit from client)
        // Find all payments for loans belonging to this client, sum their interest_delta (absolute value)
        // Since ledger entries are linked to loans, we find loans first.
        $loanIds = $client->loans->pluck('id');
        $totalInterestPaid = LoanLedgerEntry::whereIn('loan_id', $loanIds)
            ->where('type', 'payment')
            ->sum(DB::raw('ABS(interest_delta)'));

        // Calculate Insights
        $stats = [
            'total_borrowed' => $client->loans->sum('principal_initial'),
            'total_loans' => $client->loans->count(),
            'active_loans' => $client->loans->where('status', 'active')->count(),
            'completed_loans' => $client->loans->where('status', 'closed')->count(),
            'total_paid' => DB::table('payments')
                ->where('client_id', $client->id)
                ->sum('amount'),
            'total_interest_paid' => $totalInterestPaid,
            'current_arrears_count' => $client->loans
                ->where('status', 'active')
                ->filter(function($l) {
                     return $l->next_due_date && $l->next_due_date < now();
                })->count()
        ];

        return Inertia::render('Clients/Show', [
            'client' => $client,
            'stats' => $stats
        ]);
    }

    public function edit(Client $client)
    {
        return Inertia::render('Clients/Edit', [
            'client' => $client
        ]);
    }

    public function update(Request $request, Client $client)
    {
        $validated = $request->validate([
            'national_id' => 'required|unique:clients,national_id,' . $client->id,
            'first_name' => 'required',
            'last_name' => 'required',
            'phone' => 'nullable',
            'email' => 'nullable|email',
            'address' => 'nullable',
            'notes' => 'nullable',
            'status' => 'required|in:active,inactive'
        ]);

        $client->update($validated);

        return redirect()->route('clients.show', $client);
    }

    public function destroy(Client $client)
    {
        $client->delete();
        return redirect()->route('clients.index');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;
use Inertia\Facades\Inertia;

class ClientController extends Controller
{
    public function index()
    {
        $clients = Client::latest()->get();
        return Inertia::render('Clients/Index', [
            'clients' => $clients
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

        Client::create($validated);

        return redirect()->route('clients.index');
    }

    public function show(Client $client)
    {
        $client->load(['loans' => function($q) {
            $q->latest();
        }]);

        return Inertia::render('Clients/Show', [
            'client' => $client
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

<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class TransactionController extends Controller
{
    protected $transactionService;

    public function __construct(TransactionService $transactionService)
    {
        $this->transactionService = $transactionService;
    }

    public function store(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'email' => 'required|exists:users,email'
        ]);

        $amount = $request->amount;
        $toAccountEmail = $request->email;

        try {
            $this->transactionService->performTransaction($amount, $toAccountEmail);
        } catch (Throwable $e) {
            return redirect('/dashboard')->with(['error' => 'There was a problem with the transaction, please try again later.']);
        }

        return redirect('/dashboard')->with(['success' => 'The transaction was successful.']);
    }

    // Will need change to take transaction directly as parameter
    public function complete(Request $request)
    {
        $transaction = Transaction::findOrFail($request->transactionId);
        try {
            if ($transaction->status != 'pending') {
                throw new \Exception('Transaction is not pending.');
            }
            $this->transactionService->sendFunds($transaction);
        } catch (Throwable $e) {
            return redirect('/dashboard')->with(['error' => 'There was a problem completing the transaction!']);
        }
        return null;
    }

    // Will need change to take transaction directly as parameter
    public function refund(Request $request)
    {
        $transaction = Transaction::findOrFail($request->transactionId);
        $user = auth()->user();
        if ($transaction->to_account_id != $user->account->id) {
            return redirect('/dashboard')->with(['error' => 'Unauthorized refund attempt.']);
        }
        if ($transaction->status != 'completed') {
            if ($transaction->status == 'pending') {
                $this->transactionService->refundFunds($transaction, true);
            } else {
                return redirect('/dashboard')->with(['error' => 'This transaction is not eligible for a refund.']);
            }
        } else {
            try {
                $this->transactionService->refundFunds($transaction);
            } catch (Throwable $e) {
                return redirect('/dashboard')->with(['error' => 'There was a problem processing your refund. Please try again later.']);
            }
        }
        return redirect('/dashboard')->with(['success' => 'The refund has been processed successfully.']);
    }
}

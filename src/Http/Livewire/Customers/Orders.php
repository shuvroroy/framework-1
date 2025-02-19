<?php

namespace Shopper\Framework\Http\Livewire\Customers;

use Livewire\Component;
use Livewire\WithPagination;

class Orders extends Component
{
    use WithPagination;

    /**
     * Customer Model.
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    public $customer;

    /**
     * Component Mount instance.
     *
     * @param \Illuminate\Database\Eloquent\Model $customer
     */
    public function mount($customer)
    {
        $this->customer = $customer;
    }

    public function render()
    {
        return view('shopper::livewire.customers.orders', [
            'orders' => $this->customer->orders()
                ->with(['customer', 'items', 'shippingAddress', 'paymentMethod'])
                ->simplePaginate(3),
        ]);
    }
}

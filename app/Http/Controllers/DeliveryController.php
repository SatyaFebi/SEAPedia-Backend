<?php

namespace App\Http\Controllers;

use App\Models\DeliveryJob;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DeliveryController extends Controller
{
    /**
     * List all orders available for drivers to take.
     * Only orders with status 'Menunggu Pengirim' and no existing delivery job.
     */
    public function availableJobs(): JsonResponse
    {
        $jobs = Order::where('status', 'Menunggu Pengirim')
            ->whereDoesntHave('deliveryJob')
            ->with(['store', 'items.product', 'buyer'])
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json($jobs->map(fn ($o) => $this->formatJobListing($o)));
    }

    /**
     * View details of a specific available job.
     */
    public function show(string $orderId): JsonResponse
    {
        $order = Order::where('status', 'Menunggu Pengirim')
            ->with(['store', 'items.product', 'buyer', 'statusHistory'])
            ->findOrFail($orderId);

        return response()->json($this->formatJobListing($order, detailed: true));
    }

    /**
     * Driver takes a job. Atomic — prevents two drivers from taking the same order.
     */
    public function takeJob(Request $request, string $orderId): JsonResponse
    {
        $driver = auth()->user();

        try {
            $result = DB::transaction(function () use ($driver, $orderId) {
                // Lock the order row for update to prevent race condition
                $order = Order::lockForUpdate()->findOrFail($orderId);

                if ($order->status !== 'Menunggu Pengirim') {
                    throw new \Exception('Pesanan ini tidak tersedia untuk diambil.');
                }

                if ($order->driver_id !== null) {
                    throw new \Exception('Pesanan ini sudah diambil oleh driver lain.');
                }

                $earnedAmount = round($order->delivery_fee * 0.8, 2);

                // This INSERT will fail with unique violation if another driver sneaks in
                $job = DeliveryJob::create([
                    'id' => (string) Str::uuid(),
                    'order_id' => $order->id,
                    'driver_id' => $driver->id,
                    'status' => 'Active',
                    'earned_amount' => $earnedAmount,
                    'taken_at' => now(),
                ]);

                $order->driver_id = $driver->id;
                $order->status = 'Sedang Dikirim';
                $order->save();

                OrderStatusHistory::create([
                    'id' => (string) Str::uuid(),
                    'order_id' => $order->id,
                    'status' => 'Sedang Dikirim',
                    'changed_by_role' => 'Driver',
                ]);

                return ['job' => $job, 'order' => $order->load(['store', 'items.product', 'buyer', 'statusHistory'])];
            });

            return response()->json([
                'message' => 'Pekerjaan berhasil diambil! Segera ambil barang di toko.',
                'job' => $this->formatDeliveryJob($result['job'], $result['order']),
            ]);
        } catch (UniqueConstraintViolationException $e) {
            return response()->json(['message' => 'Pesanan ini baru saja diambil oleh driver lain.'], 409);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * Driver confirms delivery is complete.
     */
    public function completeJob(Request $request, string $orderId): JsonResponse
    {
        $driver = auth()->user();

        $job = DeliveryJob::where('order_id', $orderId)
            ->where('driver_id', $driver->id)
            ->where('status', 'Active')
            ->firstOrFail();

        $order = Order::findOrFail($orderId);

        if ($order->status !== 'Sedang Dikirim') {
            return response()->json(['message' => 'Pesanan ini tidak dalam status pengiriman.'], 400);
        }

        DB::transaction(function () use ($job, $order) {
            $order->status = 'Pesanan Selesai';
            $order->save();

            $job->status = 'Completed';
            $job->completed_at = now();
            $job->save();

            OrderStatusHistory::create([
                'id' => (string) Str::uuid(),
                'order_id' => $order->id,
                'status' => 'Pesanan Selesai',
                'changed_by_role' => 'Driver',
            ]);
        });

        $job->refresh();
        $order->load(['store', 'items.product', 'buyer', 'statusHistory']);

        return response()->json([
            'message' => 'Pengiriman selesai! Komisi telah ditambahkan.',
            'job' => $this->formatDeliveryJob($job, $order),
        ]);
    }

    /**
     * List driver's own jobs (active + completed).
     */
    public function myJobs(): JsonResponse
    {
        $driver = auth()->user();

        $jobs = DeliveryJob::where('driver_id', $driver->id)
            ->with(['order.store', 'order.items.product', 'order.buyer', 'order.statusHistory'])
            ->orderBy('taken_at', 'desc')
            ->get();

        return response()->json($jobs->map(fn ($job) => $this->formatDeliveryJob($job, $job->order)));
    }

    /**
     * Driver earnings summary.
     */
    public function earnings(): JsonResponse
    {
        $driver = auth()->user();

        $completed = DeliveryJob::where('driver_id', $driver->id)
            ->where('status', 'Completed')
            ->get();

        $totalEarnings = $completed->sum('earned_amount');
        $completedCount = $completed->count();

        $active = DeliveryJob::where('driver_id', $driver->id)
            ->where('status', 'Active')
            ->with(['order.store'])
            ->first();

        return response()->json([
            'total_earnings' => (float) $totalEarnings,
            'completed_count' => $completedCount,
            'active_job' => $active ? $this->formatDeliveryJob($active, $active->order) : null,
            'earning_rule' => 'Driver mendapat 80% dari ongkos kirim setiap pengiriman yang berhasil diselesaikan.',
        ]);
    }

    private function formatJobListing(Order $order, bool $detailed = false): array
    {
        $data = [
            'order_id' => $order->id,
            'store_name' => $order->store?->store_name ?? 'Toko Tidak Dikenal',
            'buyer_name' => $order->buyer?->name ?? 'Pembeli Tidak Dikenal',
            'shipping_address' => $order->shipping_address,
            'delivery_method' => $order->delivery_method,
            'delivery_fee' => (float) $order->delivery_fee,
            'driver_earning' => round($order->delivery_fee * 0.8, 2),
            'status' => $order->status,
            'created_at' => $order->created_at->toDateTimeString(),
            'items_count' => $order->items->count(),
        ];

        if ($detailed) {
            $data['items'] = $order->items->map(fn ($item) => [
                'name' => $item->product?->name ?? 'Produk Terhapus',
                'quantity' => (int) $item->quantity,
                'price' => (float) $item->price_at_checkout,
            ])->toArray();

            $data['status_history'] = $order->statusHistory->map(fn ($h) => [
                'status' => $h->status,
                'timestamp' => $h->created_at->toDateTimeString(),
                'changed_by_role' => $h->changed_by_role,
            ])->toArray();
        }

        return $data;
    }

    private function formatDeliveryJob(DeliveryJob $job, Order $order): array
    {
        return [
            'job_id' => $job->id,
            'order_id' => $order->id,
            'status' => $job->status,
            'order_status' => $order->status,
            'store_name' => $order->store?->store_name ?? 'Toko Tidak Dikenal',
            'buyer_name' => $order->buyer?->name ?? 'Pembeli Tidak Dikenal',
            'shipping_address' => $order->shipping_address,
            'delivery_method' => $order->delivery_method,
            'delivery_fee' => (float) $order->delivery_fee,
            'earned_amount' => (float) $job->earned_amount,
            'taken_at' => $job->taken_at?->toDateTimeString(),
            'completed_at' => $job->completed_at?->toDateTimeString(),
            'status_history' => $order->relationLoaded('statusHistory')
                ? $order->statusHistory->map(fn ($h) => [
                    'status' => $h->status,
                    'timestamp' => $h->created_at->toDateTimeString(),
                    'changed_by_role' => $h->changed_by_role,
                ])->toArray()
                : [],
        ];
    }
}

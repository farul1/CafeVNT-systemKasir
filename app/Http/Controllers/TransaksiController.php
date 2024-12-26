<?php

namespace App\Http\Controllers;

use App\Models\Transaksi;
use App\Models\DetailTransaksi;
use App\Models\Menu;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\TransaksiExport;
use Barryvdh\DomPDF\Facade as PDF;
use Midtrans\Config;
use Midtrans\CoreApi;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;
use App\Models\LogUser;
use App\Models\User;




class TransaksiController extends Controller
{
    // Menampilkan semua transaksi kasir
    public function index(Request $request)
    {
        $request->validate([
            'date1' => 'nullable|date',
            'date2' => 'nullable|date|after_or_equal:date1',
        ]);

        $query = Transaksi::where('nama_pegawai', auth()->user()->nama);

        if ($request->filled('date1') && $request->filled('date2')) {
            $query->whereBetween('created_at', [$request->date1, $request->date2]);
        }

        $transaksis = $query->latest()->paginate(10)->withQueryString();
        Session::put('transaksis_query', $query->toSql());

        return view('dashboard.cashier.cashier', [
            'title' => 'Dashboard | Cashier',
            'transaksis' => $transaksis,
        ]);
    }

    public function create()
    {
        $menus = Menu::all();
        $title = 'Tambah Transaksi Baru';
        return view('dashboard.cashier.create', compact('menus', 'title'));
    }

    public function store(Request $request)
    {
        try {
            Log::info('Data yang diterima:', $request->all());

            $statusPembayaran = $request->metode_pembayaran === 'cash' ? 'paid' : ($request->status_pembayaran ?: 'pending');

            $validatedData = $request->validate([
                'nama_pelanggan' => 'required|string|max:255',
                'total_harga' => 'required|numeric|min:0',
                'status_pembayaran' => 'pending',
                'metode_pembayaran' => 'required|in:cash,qr',
                'nama_pegawai' => 'required|string|max:255',
                'jumlah' => 'required|array',
                'jumlah.*' => 'nullable|numeric|min:0',
            ]);

            DB::beginTransaction();

            $transaksi = new Transaksi();
            $transaksi->nama_pelanggan = $request->nama_pelanggan;
            $transaksi->total_harga = $request->total_harga;
            $transaksi->status_pembayaran = $statusPembayaran;
            $transaksi->metode_pembayaran = $request->metode_pembayaran;
            $transaksi->nama_pegawai = $request->nama_pegawai;

            if (empty($transaksi->order_id)) {
                $transaksi->order_id = 'ORD' . Str::random(8);
            }

            $transaksi->save();
            Log::info('Transaksi berhasil disimpan: ' . $transaksi->id);

            $totalHarga = 0;
            foreach ($request->jumlah as $menu_id => $jumlah) {
                if ($jumlah == 0 || $jumlah == null) {
                    continue;
                }

                $menu = Menu::find($menu_id);

                if (!$menu) {
                    Log::error('Menu tidak ditemukan: ' . $menu_id);
                    DB::rollback();
                    return back()->with('error', 'Menu dengan ID ' . $menu_id . ' tidak ditemukan.');
                }

                if ($menu->ketersediaan < $jumlah) {
                    Log::error('Stok tidak mencukupi untuk menu: ' . $menu->nama_menu);
                    DB::rollback();
                    return back()->with('error', 'Stok menu ' . $menu->nama_menu . ' tidak mencukupi.');
                }

                $menu->ketersediaan -= $jumlah;
                $menu->save();

                $transaksiMenu = new DetailTransaksi();
                $transaksiMenu->transaksi_id = $transaksi->id;
                $transaksiMenu->menu_id = $menu_id;
                $transaksiMenu->jumlah = $jumlah;
                $transaksiMenu->harga = $menu->harga;
                $transaksiMenu->save();

                $totalHarga += $menu->harga * $jumlah;
            }

            $transaksi->update(['total_harga' => $totalHarga]);

            DB::commit();
            Log::info('Transaksi dan detail berhasil disimpan.');

            return redirect()->route('dashboard.cashier.index')->with('success', 'Transaksi berhasil disimpan!');
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Terjadi kesalahan: ' . $e->getMessage());

            return back()->with('error', 'Terjadi kesalahan saat menyimpan transaksi.')->withInput();
        }
    }

    public function qrPayment(Transaksi $transaksi)
    {
        Config::$serverKey = env('MIDTRANS_SERVER_KEY');
        Config::$isProduction = env('MIDTRANS_IS_PRODUCTION', false);
        Config::$isSanitized = true;
        Config::$is3ds = true;

        $midtrans_transaction = [
            'transaction_details' => [
                'order_id' => $transaksi->token,
                'gross_amount' => $transaksi->total_harga,
            ],
            'payment_type' => 'qris',
            'callbacks' => [
                'finish' => route('midtrans.payment'),
            ],
        ];

        try {
            $response = CoreApi::charge($midtrans_transaction);

            if (isset($response->actions[0]->url)) {
                $payment_url = $response->actions[0]->url;
                return redirect()->away($payment_url);
            } else {
                return back()->with('error', 'Gagal mendapatkan URL pembayaran.');
            }
        } catch (\Exception $e) {
            \Log::error('Error saat membuat pembayaran QR: ' . $e->getMessage());
            return back()->with('error', 'Gagal membuat transaksi dengan Midtrans: ' . $e->getMessage());
        }
    }

    public function show($id)
    {
        $transaksi = Transaksi::with('detailTransaksi.menu')->find($id);
        if (!$transaksi) {
            return redirect()->route('dashboard.cashier.index')->with('error', 'Transaksi tidak ditemukan.');
        }
        return view('dashboard.cashier.show', compact('transaksi'));
    }

    public function exportExcel()
    {
        $log_user = [
            'username' => auth()->user()->username,
            'role' => auth()->user()->role,
            'deskripsi' => auth()->user()->username . ' melakukan ekspor (Excel) data transaksi pemesanan'
        ];

        LogUser::create($log_user);

        return Excel::download(new TransaksiExport, Str::random(10) . '.xlsx');
    }

    public function exportPDF()
    {
        // Pastikan data transaksi ada di session
        $data_transaksi = Session::get('transaksis');

        // Cek jika data transaksi kosong
        if (!$data_transaksi) {
            // Berikan respon atau handle jika data transaksi tidak ada
            return response()->json(['message' => 'Data transaksi tidak ditemukan.'], 404);
        }

        // Ambil data user terkait dengan username yang sedang login
        $data_pegawai = User::where('username', auth()->user()->username)->first(); // Ambil user berdasarkan username yang sedang login

        if (!$data_pegawai) {
            // Jika user tidak ditemukan, tangani dengan memberi respon
            return response()->json(['message' => 'Data pengguna tidak ditemukan.'], 404);
        }

        // Log aktivitas user
        $log_user = [
            'username' => auth()->user()->username,
            'role' => auth()->user()->role,
            'deskripsi' => auth()->user()->username . ' melakukan ekspor (PDF) data transaksi pemesanan'
        ];

        // Simpan log aktivitas
        LogUser::create($log_user);

        // Siapkan data untuk PDF
        $data = [
            'nama_pegawai' => $data_pegawai->nama, // Nama user
            'role' => $data_pegawai->role, // Role user
            'transaksis' => $data_transaksi // Data transaksi
        ];

        // Generate PDF dengan data yang sudah disiapkan
        $pdf = PDF::loadView('pdf.cashier-pdf', $data);

        // Download file PDF dengan nama acak
        return $pdf->download(Str::random(10) . '.pdf');
    }


    public function createTransaction(Request $request)
{
    // Log data yang diterima untuk debugging
    Log::info('Data yang diterima:', $request->all());

    // Validasi input berdasarkan metode pembayaran
    $rules = [
        'total_harga' => 'required|numeric|min:1',
        'nama_pelanggan' => 'required|string|max:255',
    ];

    // Lakukan validasi
    $validator = Validator::make($request->all(), $rules);

    // Jika validasi gagal
    if ($validator->fails()) {
        return response()->json(['error' => $validator->errors()], 422);
    }

    // Generate Order ID (Unique)
    $orderId = 'INV-' . time() . '-' . Str::random(6);

    // Persiapkan parameter untuk Snap Token jika metode pembayaran QR
    $midtransParams = null;
    $snapToken = null;
    if ($request->metode_pembayaran === 'qr') {
        $midtransParams = [
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => $request->total_harga,
            ],
            'customer_details' => [
                'first_name' => $request->nama_pelanggan,
            ],
        ];

        // Menghasilkan Snap Token
        $snapToken = \Midtrans\Snap::getSnapToken($midtransParams);
    }

    DB::beginTransaction(); // Memulai transaksi untuk menyimpan data transaksi

    try {
        // Simpan transaksi ke database
        $transaksi = new Transaksi();
        $transaksi->order_id = $orderId;
        $transaksi->nama_pelanggan = $request->nama_pelanggan;
        $transaksi->total_harga = $request->total_harga;
        $transaksi->status_pembayaran = 'pending';  // Status sementara, karena belum dibayar
        $transaksi->metode_pembayaran = $request->metode_pembayaran;  // Menyimpan metode pembayaran

        // Jika metode pembayaran adalah cash, simpan nama pegawai
        if ($request->metode_pembayaran === 'cash') {
            $transaksi->nama_pegawai = $request->nama_pegawai;
        }

        $transaksi->save(); // Simpan transaksi ke database

        // Commit transaksi database
        DB::commit();

        // Kembalikan Snap Token ke frontend jika metode QR
        if ($snapToken) {
            return response()->json([
                'token' => $snapToken,
                'order_id' => $orderId, // Bisa mengembalikan order_id untuk referensi
            ]);
        }

        // Jika metode cash, kembalikan hanya order_id
        return response()->json([
            'order_id' => $orderId, // Kembalikan order_id sebagai referensi
        ]);

    } catch (\Exception $e) {
        DB::rollback(); // Jika terjadi error, rollback perubahan
        \Log::error('Error saat menyimpan transaksi: ' . $e->getMessage());
        return response()->json(['error' => $e->getMessage()], 500);
    }
}
    // Mengelola callback pembayaran sukses dari Midtrans
    public function paymentSuccess(Request $request)
    {
        $transaksi = Transaksi::where('token', $request->input('order_id'))->first();

        if ($transaksi) {
            $transaksi->update(['status_pembayaran' => 'Paid']);

            foreach ($transaksi->detailTransaksi as $detail) {
                $menu = $detail->menu;
                if ($menu && $menu->ketersediaan >= $detail->jumlah) {
                    $menu->decrement('ketersediaan', $detail->jumlah);
                } else {
                    return response()->json(['error' => 'Stok menu tidak cukup.'], 400);
                }
            }

            return response()->json(['message' => 'Pembayaran berhasil diproses.'], 200);
        }

        return response()->json(['error' => 'Transaksi tidak ditemukan.'], 404);
    }

}

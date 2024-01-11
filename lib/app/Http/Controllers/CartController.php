<?php

namespace App\Http\Controllers;

use App\Models\VpOrder;
use App\Models\VpProduct;
use Illuminate\Http\Request;
use Gloudemans\Shoppingcart\Facades\Cart;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class CartController extends Controller
{
    public function getAddCart($id)
    {
        $product = VpProduct::find($id);
        Cart::add(['id' => $id, 'name' => $product->prod_name, 'qty' => 1,
                    'price' => $product->prod_price, 'weight' => 550, 'options' => ['img' => $product->prod_img]]);

        return redirect('cart/show');
    }
    public function getShowCart()
    {
        $total = Cart::total();
        $products = Cart::content();
        return view('frontend.cart', compact('products', 'total'));
    }
    public function getDeleteCart($id)
    {
        if($id == 'all') {
            Cart::destroy();
        } else {
            Cart::remove($id);
        }

        return back();
    }
    public function getUpdateCart(Request $request)
    {
        $rowId = $request->rowId;
        $quantity = $request->quantity;

        Cart::update($rowId, $quantity);
    }
    public function postPayCart(Request $request)
    {
        // add table order
        $order_code = 'order_'.random_int(10000, 99999);

        $order = new VpOrder;
        $order->name  = $request->name;
        $order->address = $request->add;
        $order->email = $request->email;
        $order->phone = $request->phone;
        $order->total_price = Cart::total();
        $order->total_products = Cart::content()->pluck('name')->implode('; ');
        $order->placed_order_date = now()->format('d/m/Y');
        $order->user_id = Auth::id();
        $order->code =  $order_code;
        $order->save();

        $data['info'] = $request->all();
        $email = $request->email;
        $name = $request->name;
        $data['cart'] = Cart::content();
        $data['total'] = Cart::total();
        Mail::send('frontend.email', $data, function ($message) use ($email, $name) {
            $message->from('dungli1221@gmail.com', 'Mạnh Dũng');

            $message->to($email, $name);

            $message->subject('Xác nhận hóa đơn mua hàng MLDShop');

        });

        $loan_amount =  (int) (str_ireplace(',', '', Cart::total()));

        Cart::destroy();
        $vnp_Url = "http://sandbox.vnpayment.vn/paymentv2/vpcpay.html";
        $vnp_Returnurl = route('responseOrder')."?code=".$order_code;
        $vnp_TmnCode = "2TNEXMQ8"; //Mã website tại VNPAY
        $vnp_HashSecret = "NTELFBCCDPWBIXLZQRTUCNLOYNVFNANA"; //Chuỗi bí mật
        $vnp_TxnRef = $order_code; //Mã đơn hàng. Trong thực tế Merchant cần insert đơn hàng vào DB và gửi mã này sang VNPAY
        $vnp_OrderInfo = "Thanh toán đơn đặt hàng ";
        $vnp_OrderType = "Thanh toán đơn đặt hàng";
        $vnp_Amount = $loan_amount * 100;
        $vnp_Locale = "VN";
        $vnp_IpAddr = $_SERVER['REMOTE_ADDR'];
        $inputData = array(
            "vnp_Version" => "2.1.0",
            "vnp_TmnCode" => $vnp_TmnCode,
            "vnp_Amount" => $vnp_Amount,
            "vnp_Command" => "pay",
            "vnp_CreateDate" => date('YmdHis'),
            "vnp_CurrCode" => "VND",
            "vnp_IpAddr" => $vnp_IpAddr,
            "vnp_Locale" => $vnp_Locale,
            "vnp_OrderInfo" => $vnp_OrderInfo,
            "vnp_OrderType" => $vnp_OrderType,
            "vnp_ReturnUrl" => $vnp_Returnurl,
            "vnp_TxnRef" => $vnp_TxnRef,
        );
        // dd($inputData);

        if (isset($vnp_BankCode) && $vnp_BankCode != "") {
            $inputData['vnp_BankCode'] = $vnp_BankCode;
        }

        ksort($inputData);
        $query = "";
        $i = 0;
        $hashdata = "";
        foreach ($inputData as $key => $value) {
            if ($i == 1) {
                $hashdata .= '&' . urlencode($key) . "=" . urlencode($value);
            } else {
                $hashdata .= urlencode($key) . "=" . urlencode($value);
                $i = 1;
            }
            $query .= urlencode($key) . "=" . urlencode($value) . '&';
        }

        $vnp_Url = $vnp_Url . "?" . $query;

        if (isset($vnp_HashSecret)) {
            $vnpSecureHash =   hash_hmac('sha512', $hashdata, $vnp_HashSecret);//
            $vnp_Url .= 'vnp_SecureHash=' . $vnpSecureHash;
        }


        return redirect($vnp_Url);
        // return redirect('complete');
    }
    public function getComplete()
    {
        return view('frontend.complete');
    }




    public function response(Request $request)
    {
        $code = $request->code;
        $order =VpOrder::where("code",$code)->first();
        if($order != null) {
            VpOrder::where("code",$code)->update([
                'order_status' => "Chờ xác nhận"
            ]);
            return redirect('complete');
        }

    }
}

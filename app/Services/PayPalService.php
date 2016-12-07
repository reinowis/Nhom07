<?php

namespace App\Services;
use PayPal\Api\CreateProfileResponse;
use PayPal\Api\InputFields;
use PayPal\Api\Presentation;
use PayPal\Exception\PayPalConnectionException;
use PayPal\Rest\ApiContext;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Api\Item;
use PayPal\Api\ItemList;
use PayPal\Api\Amount;
use PayPal\Api\Transaction;
use PayPal\Api\RedirectUrls;
use PayPal\Api\Payment;
use PayPal\Api\Payer;
use PayPal\Api\PaymentExecution;
use PayPal\Api\WebProfile;
use Request;
class PayPalService
{
    // Chứa context của API
    private $apiContext;
    // Chứa danh sách các item (mặt hàng)
    private $itemList;
    // Đơn vị tiền thanh toán
    private $paymentCurrency;
    // Tổng tiền của đơn hàng
    private $totalAmount;
    // Đường dẫn để xử lý một thanh toán thành công
    private $returnUrl;
    // Đường dẫn để xử lý khi người dùng bấm cancel (không thanh toán)
    private $cancelUrl;
    public function __construct()
    {
        // Đọc các cài đặt trong file config
        $paypalConfigs = config('paypal');

        // Khởi tạo ngữ cảnh
        $this->apiContext = new ApiContext(
            new OAuthTokenCredential(
                $paypalConfigs['client_id'],
                $paypalConfigs['secret']
            )
        );

        // Set mặc định đơn vị tiền để thanh toán
        $this->paymentCurrency = "USD";

        // Khởi tạo total amount
        $this->totalAmount = 0;
    }
    /**
     * Set payment currency
     *
     * @param string $currency String name of currency
     * @return self
     */
    public function setCurrency($currency)
    {
        $this->paymentCurrency = $currency;

        return $this;
    }

    /**
     * Get current payment currency
     *
     * @return string Current payment currency
     */
    public function getCurrency()
    {
        return $this->paymentCurrency;
    }
    /**
     * Add item to list
     *
     * @param array $itemData Array item data
     * @return self
     */
    public function setItem($itemData)
    {
        // Kiểm tra xem item được thêm vào là một hay một
        // mảng các item. Nếu chỉ là 1 item, thì chúng ta sẽ
        // cho nó thành một mảng item rồi foreach. Việc này giúp
        // chúng ta có thể thêm một hay nhiều item cùng lúc
        if (count($itemData) === count($itemData, COUNT_RECURSIVE)) {
            $itemData = [$itemData];
        }

        // Duyệt danh sách các item
        foreach ($itemData as $data) {
            // Khởi tạo item
            $item = new Item();

            // Set tên của item
            $item->setName($data['name'])
                ->setCurrency($this->paymentCurrency) // Đơn vị tiền của item
                ->setSku($data['sku']) // ID của item
                ->setQuantity($data['quantity']) // Số lượng
                ->setPrice($data['price']); // Giá
            // Thêm item vào danh sách
            $this->itemList[] = $item;
            // Tính tổng đơn hàng
            $this->totalAmount += $data['price'] * $data['quantity'];
        }

        return $this;
    }

    /**
     * Get list item
     *
     * @return array List item
     */
    public function getItemList()
    {
        return $this->itemList;
    }
    public function getTotalAmount()
    {
        return $this->totalAmount;
    }
    /**
     * Set return URL
     *
     * @param string $url Return URL for payment process complete
     * @return self
     */
    public function setReturnUrl($url)
    {
        $this->returnUrl = $url;

        return $this;
    }

    /**
     * Get return URL
     *
     * @return string Return URL
     */
    public function getReturnUrl()
    {
        return $this->returnUrl;
    }
    /**
     * Set cancel URL
     *
     * @param $url Cancel URL for payment
     * @return self
     */
    public function setCancelUrl($url)
    {
        $this->cancelUrl = $url;

        return $this;
    }

    /**
     * Get cancel URL of payment
     *
     * @return string Cancel URL
     */
    public function getCancelUrl()
    {
        return $this->cancelUrl;
    }
    /**
     * Create payment
     *
     * @param string $transactionDescription Description for transaction
     * @return mixed Paypal checkout URL or false
     */
    public function createPayment($transactionDescription)
    {
        //dd($this->getProfileId());
        $checkoutUrl = false;

        // Chọn kiểu thanh toán.
        $payer = new Payer();
        $payer->setPaymentMethod('paypal');
        //profile
        //$createprofile = $profile->create($this->apiContext);
        $itemList = new ItemList();
        $itemList->setItems($this->itemList);
        // Tổng tiền và kiểu tiền sẽ sử dụng để thanh toán.
        // Bạn nên đồng nhất kiểu tiền của item và kiểu tiền của đơn hàng
        // tránh trường hợp đơn vị tiền của item là JPY nhưng của đơn hàng
        // lại là USD nhé.
        $amount = new Amount();
        $amount->setCurrency($this->paymentCurrency)
            ->setTotal($this->totalAmount);
        //webprolfile
        /*$input_fiels = new InputFields();
        $input_fiels->setNoShipping(1);
        $presentation = new Presentation();
        $presentation->setBrandName('nap_tien_vao_tai_khoan');
        $presentation->setLocaleCode('US');
        $presentation->setLogoImage(asset('/images/home/logo.png'));
        $profile = new WebProfile();
        $profile->setName('nap_tien_vao_tai_khoan');
        $profile->setPresentation($presentation);
        $profile->setInputFields($input_fiels);
        $profile->setTemporary(false);
        $webprofile = $profile->create($this->apiContext);
        dd($webprofile->getId());
        */
        // Transaction
        $transaction = new Transaction();
        $transaction->setAmount($amount)
            ->setItemList($itemList)
            ->setDescription($transactionDescription);

        // Đường dẫn để xử lý một thanh toán thành công.
        $redirectUrls = new RedirectUrls();

        // Kiểm tra xem có tồn tại đường dẫn khi người dùng hủy thanh toán
        // hay không. Nếu không, mặc định chúng ta sẽ dùng luôn $redirectUrl
        if (is_null($this->cancelUrl)) {
            $this->cancelUrl = $this->returnUrl;
        }

        $redirectUrls->setReturnUrl($this->returnUrl)
            ->setCancelUrl($this->cancelUrl);

        // Khởi tạo một payment

        $payment = new Payment();
        $payment->setIntent('Sale')
            ->setExperienceProfileId('XP-4GCX-GFES-BBVX-GWTD')
            ->setPayer($payer)
            ->setRedirectUrls($redirectUrls)
            ->setTransactions([$transaction]);

        // Thực hiện việc tạo payment
        try {
            $payment->create($this->apiContext);
        }
        catch (PayPalConnectionException $ex) {
            echo $ex->getCode(); // Prints the Error Code
            echo $ex->getData(); // Prints the detailed error message
            die($ex);
        } catch (Exception $ex) {
            die($ex);
        }

        // Nếu việc thanh tạo một payment thành công. Chúng ta sẽ nhận
        // được một danh sách các đường dẫn liên quan đến việc
        // thanh toán trên PayPal
        foreach ($payment->getLinks() as $link) {
            // Duyệt từng link và lấy link nào có rel
            // là approval_url rồi gán nó vào $checkoutUrl
            // để chuyển hướng người dùng đến đó.
            if ($link->getRel() == 'approval_url') {
                $checkoutUrl = $link->getHref();
                // Lưu payment ID vào session để kiểm tra
                // thanh toán ở function khác
                session(['paypal_payment_id' => $payment->getId()]);

                break;
            }
        }

        // Trả về url thanh toán để thực hiện chuyển hướng
        return $checkoutUrl;
    }
    /**
     * Get payment status
     *
     * @return mixed Object payment details or false
     */

    public function getPaymentStatus()
    {
        // Khởi tạo request để lấy một số query trên
        // URL trả về từ PayPal
        $request = Request::all();

        // Lấy Payment ID từ session
        $paymentId = session('paypal_payment_id');
        // Xóa payment ID đã lưu trong session
        session()->forget('paypal_payment_id');

        // Kiểm tra xem URL trả về từ PayPal có chứa
        // các query cần thiết của một thanh toán thành công
        // hay không.
        if (empty($request['PayerID']) || empty($request['token'])) {
            return false;
        }

        // Khởi tạo payment từ Payment ID đã có
        $payment = Payment::get($paymentId, $this->apiContext);

        // Thực thi payment và lấy payment detail
        $paymentExecution = new PaymentExecution();
        $paymentExecution->setPayerId($request['PayerID']);

        $paymentStatus = $payment->execute($paymentExecution, $this->apiContext);

        return $paymentStatus;
    }
    public function getPaymentList($limit = 10, $offset = 0)
    {
        $params = [
            'count' => $limit,
            'start_index' => $offset
        ];

        try {
            $payments = Payment::all($params, $this->apiContext);
        } catch (\PayPal\Exception\PPConnectionException $paypalException) {
            throw new \Exception($paypalException->getMessage());
        }

        return $payments;
    }
    public function getPaymentDetails($paymentId)
    {
        try {
            $paymentDetails = Payment::get($paymentId, $this->apiContext);
        } catch (\PayPal\Exception\PPConnectionException $paypalException) {
            throw new \Exception($paypalException->getMessage());
        }

        return $paymentDetails;
    }
}
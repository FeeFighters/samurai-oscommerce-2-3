<?php

/*
  $Id$

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2008 osCommerce

  Released under the GNU General Public License
 */
require_once 'feefighter/lib/Samurai.php';

class feefighter {

    var $code, $title, $description, $enabled;

// class constructor
    function feefighter() {
        global $order;
        global $paymentToken;
        $this->signature = 'FEEFIGHTER|1.0|2.2';

        $this->code = 'feefighter';
        $this->title = MODULE_PAYMENT_FEEFIGHTER_TEXT_TITLE;
        $this->public_title = MODULE_PAYMENT_FEEFIGHTER_TEXT_PUBLIC_TITLE;
        $this->description = MODULE_PAYMENT_FEEFIGHTER_TEXT_DESCRIPTION;
        $this->sort_order = MODULE_PAYMENT_FEEFIGHTER_SORT_ORDER;
        $this->enabled = ((MODULE_PAYMENT_FEEFIGHTER_STATUS == 'True') ? true : false);

        if ((int) MODULE_PAYMENT_FEEFIGHTER_PREPARE_ORDER_STATUS_ID > 0) {
            $this->order_status = MODULE_PAYMENT_FEEFIGHTER_PREPARE_ORDER_STATUS_ID;
        }
        $this->form_action_url = 'https://api.samurai.feefighters.com/v1/payment_methods';
        if (is_object($order))
            $this->update_status();
        if (isset($_REQUEST['payment_method_token'])) {
            $_SESSION['payment_method_token'] = $_REQUEST['payment_method_token'];
            $paymentToken = $_REQUEST['payment_method_token'];
        }
    }

// class methods
    function update_status() {
        return false;
    }

    function javascript_validation() {
        return false;
    }

    function selection() {
        global $cart_FEEFIGHTER_ID;

        if (tep_session_is_registered('cart_FEEFIGHTER_ID')) {
            $order_id = substr($cart_FEEFIGHTER_ID, strpos($cart_FEEFIGHTER_ID, '-') + 1);

            $check_query = tep_db_query('select orders_id from ' . TABLE_ORDERS_STATUS_HISTORY . ' where orders_id = "' . (int) $order_id . '" limit 1');

            if (tep_db_num_rows($check_query) < 1) {
                tep_db_query('delete from ' . TABLE_ORDERS . ' where orders_id = "' . (int) $order_id . '"');
                tep_db_query('delete from ' . TABLE_ORDERS_TOTAL . ' where orders_id = "' . (int) $order_id . '"');
                tep_db_query('delete from ' . TABLE_ORDERS_STATUS_HISTORY . ' where orders_id = "' . (int) $order_id . '"');
                tep_db_query('delete from ' . TABLE_ORDERS_PRODUCTS . ' where orders_id = "' . (int) $order_id . '"');
                tep_db_query('delete from ' . TABLE_ORDERS_PRODUCTS_ATTRIBUTES . ' where orders_id = "' . (int) $order_id . '"');
                tep_db_query('delete from ' . TABLE_ORDERS_PRODUCTS_DOWNLOAD . ' where orders_id = "' . (int) $order_id . '"');

                tep_session_unregister('cart_FEEFIGHTER_ID');
            }
        }

        return array('id' => $this->code,
            'module' => $this->public_title);
    }

    function pre_confirmation_check() {
        global $cartID, $cart;

        if (empty($cart->cartID)) {
            $cartID = $cart->cartID = $cart->generate_cart_id();
        }

        if (!tep_session_is_registered('cartID')) {
            tep_session_register('cartID');
        }
    }

    function confirmation() {
        global $order;

        for ($i = 1; $i < 13; $i++) {
            $expires_month[] = array('id' => sprintf('%02d', $i), 'text' => strftime('%B', mktime(0, 0, 0, $i, 1, 2000)));
        }

        $today = getdate();
        for ($i = $today['year']; $i < $today['year'] + 10; $i++) {
            $expires_year[] = array('id' => strftime('%y', mktime(0, 0, 0, 1, 1, $i)), 'text' => strftime('%Y', mktime(0, 0, 0, 1, 1, $i)));
        }

        $confirmation = array('fields' => array(array('title' => MODULE_PAYMENT_FEEFIGHTER_CREDIT_CARD_OWNER_FIRST_NAME,
                    'field' => tep_draw_input_field('credit_card[first_name]', $order->billing['firstname'])),
                array('title' => MODULE_PAYMENT_FEEFIGHTER_CREDIT_CARD_OWNER_LAST_NAME,
                    'field' => tep_draw_input_field('credit_card[last_name]', $order->billing['lastname'])),
                array('title' => MODULE_PAYMENT_FEEFIGHTER_CREDIT_CARD_OWNER_ADDRESS1,
                    'field' => tep_draw_textarea_field('credit_card[address_1]', $order->billing['company'] . ' ' . $order->billing['street_address'])),
                array('title' => MODULE_PAYMENT_FEEFIGHTER_CREDIT_CARD_OWNER_ADDRESS2,
                    'field' => tep_draw_textarea_field('credit_card[address_2]')),
                array('title' => MODULE_PAYMENT_FEEFIGHTER_CREDIT_CARD_OWNER_CITY,
                    'field' => tep_draw_input_field('credit_card[city]', $order->billing['city'])),
                array('title' => MODULE_PAYMENT_FEEFIGHTER_CREDIT_CARD_OWNER_STATE,
                    'field' => tep_draw_input_field('credit_card[state]', $order->billing['state'])),
                array('title' => MODULE_PAYMENT_FEEFIGHTER_CREDIT_CARD_OWNER_ZIP,
                    'field' => tep_draw_input_field('credit_card[zip]', $order->billing['postcode'])),
                array('title' => MODULE_PAYMENT_FEEFIGHTER_CREDIT_CARD_NUMBER,
                    'field' => tep_draw_input_field('credit_card[card_number]')),
                array('title' => MODULE_PAYMENT_FEEFIGHTER_CREDIT_CARD_EXPIRES,
                    'field' => tep_draw_pull_down_menu('credit_card[expiry_month]', $expires_month) . '&nbsp;' . tep_draw_pull_down_menu('credit_card[expiry_year]', $expires_year)),
                array('title' => MODULE_PAYMENT_FEEFIGHTER_CREDIT_CARD_CVV,
                    'field' => tep_draw_input_field('credit_card[cvv]', '', 'size="5" maxlength="4"'))));

        return $confirmation;
    }

    function process_button() {
        global $customer_id, $order, $sendto, $currency, $cart_FEEFIGHTER_ID, $shipping;
if (MODULE_PAYMENT_FEEFIGHTER_GATEWAY_SERVER == 'Sandbox') {
            $sandbox = true;
        } else {
            $sandbox = false;
        }
        $process_button_string = '';
        $parameters = array('item_name' => STORE_NAME,
            'shipping' => $this->format_raw($order->info['shipping_cost']),
            'tax' => $this->format_raw($order->info['tax']),
            'business' => MODULE_PAYMENT_FEEFIGHTER_ID,
            'custom' => $this->format_raw($order->info['total'] - $order->info['shipping_cost'] - $order->info['tax']),
            'currency_code' => $currency,
            'invoice' => substr($cart_FEEFIGHTER_ID, strpos($cart_FEEFIGHTER_ID, '-') + 1),
            'customer_id' => $customer_id,
            'no_note' => '1',
            'return' => tep_href_link(FILENAME_CHECKOUT_PROCESS, '', 'SSL'),
            'cancel_return' => tep_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL'),
            'redirect_url' => tep_href_link(FILENAME_CHECKOUT_PROCESS, '', 'SSL'),
            'merchant_key' => MODULE_PAYMENT_FEEFIGHTER_MERCHENT_KEY,
            'sandbox' => $sandbox
        );

        if (is_numeric($sendto) && ($sendto > 0)) {
            $parameters['address_override'] = '1';
            $parameters['first_name'] = $order->delivery['firstname'];
            $parameters['last_name'] = $order->delivery['lastname'];
            $parameters['address1'] = $order->delivery['street_address'];
            $parameters['city'] = $order->delivery['city'];
            $parameters['state'] = tep_get_zone_code($order->delivery['country']['id'], $order->delivery['zone_id'], $order->delivery['state']);
            $parameters['zip'] = $order->delivery['postcode'];
            $parameters['country'] = $order->delivery['country']['iso_code_2'];
        } else {
            $parameters['no_shipping'] = '1';
            $parameters['first_name'] = $order->billing['firstname'];
            $parameters['last_name'] = $order->billing['lastname'];
            $parameters['address1'] = $order->billing['street_address'];
            $parameters['city'] = $order->billing['city'];
            $parameters['state'] = tep_get_zone_code($order->billing['country']['id'], $order->billing['zone_id'], $order->billing['state']);
            $parameters['zip'] = $order->billing['postcode'];
            $parameters['country'] = $order->billing['country']['iso_code_2'];
        }



        //reset($parameters);
        while (list($key, $value) = each($parameters)) {
            $process_button_string .= tep_draw_hidden_field($key, $value);
        }


        return $process_button_string;
    }

    function before_process() {

        global $customer_id, $order, $order_totals, $sendto, $billto, $languages_id, $payment, $currencies, $cart, $cart_FEEFIGHTER_ID,$error_code,$description;
        global $$payment;

        if (MODULE_PAYMENT_FEEFIGHTER_GATEWAY_SERVER == 'Sandbox') {
            $sandbox = true;
        } else {
            $sandbox = false;
        }
        Samurai::setup(array(
            'sandbox' => $sandbox,
            'merchantKey' => MODULE_PAYMENT_FEEFIGHTER_MERCHENT_KEY,
            'merchantPassword' => MODULE_PAYMENT_FEEFIGHTER_MERCHENT_PASSWORD,
            'processorToken' => MODULE_PAYMENT_FEEFIGHTER_PROCESSOR_TOKEN
        ));
        $paymentMethod = Samurai_PaymentMethod::find($_GET['payment_method_token']);
 if (count($paymentMethod->errors)>'0') {
            foreach ($paymentMethod->errors as $errors) {

                $error = $errors[0];
                $error_code=$errors[0]->context;
                $description.=$error->description."\n\n";


            }
                            $_SESSION['error_description']=$description;
            tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'payment_error=' . $this->code . '&error=' . urlencode("$description"), 'NONSSL', true, false));
        }


        $paymentMethodToken = $paymentMethod->attributes['payment_method_token'];
        foreach ($order->products as $product) {
            $product_name[] = $product['name'];
        }
        $productName = implode(',', $product_name);

        $amount=$order->info['total'];
       /*********************** If you are sandbox is true round the amount so as to avoid fatal errors that occures due to panny code. Go to https://samurai.feefighters.com/developers/sandbox for more  info about Panny Codes  ************************/
        if($sandbox){
            $amount=round($amount);
        }
        /***********************************************/
        $processor = Samurai_Processor::theProcessor();
        $purchase = $processor->purchase(
                $paymentMethodToken, $amount, array(
            'descriptor' => "$productName",
            'customer_reference' => time(),
            'billing_reference' => time()
                ));

        if (!$purchase->attributes['success']) {
            foreach ($purchase->errors as $errors) {

                $error = $errors[0];
                $error_code=$errors[0]->context;
                $description.=$error->description."\n\n";


            }
                            $_SESSION['error_description']=$description;
            tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'payment_error=' . $this->code . '&error=' . urlencode("$description"), 'NONSSL', true, false));
        }




        $order_id = substr($cart_FEEFIGHTER_ID, strpos($cart_FEEFIGHTER_ID, '-') + 1);

        $check_query = tep_db_query("select orders_status from " . TABLE_ORDERS . " where orders_id = '" . (int) $order_id . "'");
        if (tep_db_num_rows($check_query)) {
            $check = tep_db_fetch_array($check_query);

            if ($check['orders_status'] == MODULE_PAYMENT_FEEFIGHTER_PREPARE_ORDER_STATUS_ID) {
                $sql_data_array = array('orders_id' => $order_id,
                    'orders_status_id' => MODULE_PAYMENT_FEEFIGHTER_PREPARE_ORDER_STATUS_ID,
                    'date_added' => 'now()',
                    'customer_notified' => '0',
                    'comments' => '');

                tep_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);
            }
        }

        tep_db_query("update " . TABLE_ORDERS . " set orders_status = '" . (MODULE_PAYMENT_FEEFIGHTER_ORDER_STATUS_ID > 0 ? (int) MODULE_PAYMENT_FEEFIGHTER_ORDER_STATUS_ID : (int) DEFAULT_ORDERS_STATUS_ID) . "', last_modified = now() where orders_id = '" . (int) $order_id . "'");

        $sql_data_array = array('orders_id' => $order_id,
            'orders_status_id' => (MODULE_PAYMENT_FEEFIGHTER_ORDER_STATUS_ID > 0 ? (int) MODULE_PAYMENT_FEEFIGHTER_ORDER_STATUS_ID : (int) DEFAULT_ORDERS_STATUS_ID),
            'date_added' => 'now()',
            'customer_notified' => (SEND_EMAILS == 'true') ? '1' : '0',
            'comments' => $order->info['comments']);

        tep_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);

// initialized for the email confirmation
        $products_ordered = '';
        $subtotal = 0;
        $total_tax = 0;

        for ($i = 0, $n = sizeof($order->products); $i < $n; $i++) {
// Stock Update - Joao Correia
            if (STOCK_LIMITED == 'true') {
                if (DOWNLOAD_ENABLED == 'true') {
                    $stock_query_raw = "SELECT products_quantity, pad.products_attributes_filename
                                FROM " . TABLE_PRODUCTS . " p
                                LEFT JOIN " . TABLE_PRODUCTS_ATTRIBUTES . " pa
                                ON p.products_id=pa.products_id
                                LEFT JOIN " . TABLE_PRODUCTS_ATTRIBUTES_DOWNLOAD . " pad
                                ON pa.products_attributes_id=pad.products_attributes_id
                                WHERE p.products_id = '" . tep_get_prid($order->products[$i]['id']) . "'";
// Will work with only one option for downloadable products
// otherwise, we have to build the query dynamically with a loop
                    $products_attributes = $order->products[$i]['attributes'];
                    if (is_array($products_attributes)) {
                        $stock_query_raw .= " AND pa.options_id = '" . $products_attributes[0]['option_id'] . "' AND pa.options_values_id = '" . $products_attributes[0]['value_id'] . "'";
                    }
                    $stock_query = tep_db_query($stock_query_raw);
                } else {
                    $stock_query = tep_db_query("select products_quantity from " . TABLE_PRODUCTS . " where products_id = '" . tep_get_prid($order->products[$i]['id']) . "'");
                }
                if (tep_db_num_rows($stock_query) > 0) {
                    $stock_values = tep_db_fetch_array($stock_query);
// do not decrement quantities if products_attributes_filename exists
                    if ((DOWNLOAD_ENABLED != 'true') || (!$stock_values['products_attributes_filename'])) {
                        $stock_left = $stock_values['products_quantity'] - $order->products[$i]['qty'];
                    } else {
                        $stock_left = $stock_values['products_quantity'];
                    }
                    tep_db_query("update " . TABLE_PRODUCTS . " set products_quantity = '" . $stock_left . "' where products_id = '" . tep_get_prid($order->products[$i]['id']) . "'");
                    if (($stock_left < 1) && (STOCK_ALLOW_CHECKOUT == 'false')) {
                        tep_db_query("update " . TABLE_PRODUCTS . " set products_status = '0' where products_id = '" . tep_get_prid($order->products[$i]['id']) . "'");
                    }
                }
            }

// Update products_ordered (for bestsellers list)
            tep_db_query("update " . TABLE_PRODUCTS . " set products_ordered = products_ordered + " . sprintf('%d', $order->products[$i]['qty']) . " where products_id = '" . tep_get_prid($order->products[$i]['id']) . "'");

//------insert customer choosen option to order--------
            $attributes_exist = '0';
            $products_ordered_attributes = '';
            if (isset($order->products[$i]['attributes'])) {
                $attributes_exist = '1';
                for ($j = 0, $n2 = sizeof($order->products[$i]['attributes']); $j < $n2; $j++) {
                    if (DOWNLOAD_ENABLED == 'true') {
                        $attributes_query = "select popt.products_options_name, poval.products_options_values_name, pa.options_values_price, pa.price_prefix, pad.products_attributes_maxdays, pad.products_attributes_maxcount , pad.products_attributes_filename
                                   from " . TABLE_PRODUCTS_OPTIONS . " popt, " . TABLE_PRODUCTS_OPTIONS_VALUES . " poval, " . TABLE_PRODUCTS_ATTRIBUTES . " pa
                                   left join " . TABLE_PRODUCTS_ATTRIBUTES_DOWNLOAD . " pad
                                   on pa.products_attributes_id=pad.products_attributes_id
                                   where pa.products_id = '" . $order->products[$i]['id'] . "'
                                   and pa.options_id = '" . $order->products[$i]['attributes'][$j]['option_id'] . "'
                                   and pa.options_id = popt.products_options_id
                                   and pa.options_values_id = '" . $order->products[$i]['attributes'][$j]['value_id'] . "'
                                   and pa.options_values_id = poval.products_options_values_id
                                   and popt.language_id = '" . $languages_id . "'
                                   and poval.language_id = '" . $languages_id . "'";
                        $attributes = tep_db_query($attributes_query);
                    } else {
                        $attributes = tep_db_query("select popt.products_options_name, poval.products_options_values_name, pa.options_values_price, pa.price_prefix from " . TABLE_PRODUCTS_OPTIONS . " popt, " . TABLE_PRODUCTS_OPTIONS_VALUES . " poval, " . TABLE_PRODUCTS_ATTRIBUTES . " pa where pa.products_id = '" . $order->products[$i]['id'] . "' and pa.options_id = '" . $order->products[$i]['attributes'][$j]['option_id'] . "' and pa.options_id = popt.products_options_id and pa.options_values_id = '" . $order->products[$i]['attributes'][$j]['value_id'] . "' and pa.options_values_id = poval.products_options_values_id and popt.language_id = '" . $languages_id . "' and poval.language_id = '" . $languages_id . "'");
                    }
                    $attributes_values = tep_db_fetch_array($attributes);

                    $products_ordered_attributes .= "\n\t" . $attributes_values['products_options_name'] . ' ' . $attributes_values['products_options_values_name'];
                }
            }
//------insert customer choosen option eof ----
            $total_weight += ($order->products[$i]['qty'] * $order->products[$i]['weight']);
            $total_tax += tep_calculate_tax($total_products_price, $products_tax) * $order->products[$i]['qty'];
            $total_cost += $total_products_price;

            $products_ordered .= $order->products[$i]['qty'] . ' x ' . $order->products[$i]['name'] . ' (' . $order->products[$i]['model'] . ') = ' . $currencies->display_price($order->products[$i]['final_price'], $order->products[$i]['tax'], $order->products[$i]['qty']) . $products_ordered_attributes . "\n";
        }

// lets start with the email confirmation
        $email_order = STORE_NAME . "\n" .
                EMAIL_SEPARATOR . "\n" .
                EMAIL_TEXT_ORDER_NUMBER . ' ' . $order_id . "\n" .
                EMAIL_TEXT_INVOICE_URL . ' ' . tep_href_link(FILENAME_ACCOUNT_HISTORY_INFO, 'order_id=' . $order_id, 'SSL', false) . "\n" .
                EMAIL_TEXT_DATE_ORDERED . ' ' . strftime(DATE_FORMAT_LONG) . "\n\n";
        if ($order->info['comments']) {
            $email_order .= tep_db_output($order->info['comments']) . "\n\n";
        }
        $email_order .= EMAIL_TEXT_PRODUCTS . "\n" .
                EMAIL_SEPARATOR . "\n" .
                $products_ordered .
                EMAIL_SEPARATOR . "\n";

        for ($i = 0, $n = sizeof($order_totals); $i < $n; $i++) {
            $email_order .= strip_tags($order_totals[$i]['title']) . ' ' . strip_tags($order_totals[$i]['text']) . "\n";
        }

        if ($order->content_type != 'virtual') {
            $email_order .= "\n" . EMAIL_TEXT_DELIVERY_ADDRESS . "\n" .
                    EMAIL_SEPARATOR . "\n" .
                    tep_address_label($customer_id, $sendto, 0, '', "\n") . "\n";
        }

        $email_order .= "\n" . EMAIL_TEXT_BILLING_ADDRESS . "\n" .
                EMAIL_SEPARATOR . "\n" .
                tep_address_label($customer_id, $billto, 0, '', "\n") . "\n\n";

        if (is_object($$payment)) {
            $email_order .= EMAIL_TEXT_PAYMENT_METHOD . "\n" .
                    EMAIL_SEPARATOR . "\n";
            $payment_class = $$payment;
            $email_order .= $payment_class->title . "\n\n";
            if ($payment_class->email_footer) {
                $email_order .= $payment_class->email_footer . "\n\n";
            }
        }

        tep_mail($order->customer['firstname'] . ' ' . $order->customer['lastname'], $order->customer['email_address'], EMAIL_TEXT_SUBJECT, $email_order, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);

// send emails to other people
        if (SEND_EXTRA_ORDER_EMAILS_TO != '') {
            tep_mail('', SEND_EXTRA_ORDER_EMAILS_TO, EMAIL_TEXT_SUBJECT, $email_order, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);
        }

// load the after_process function from the payment modules
        $this->after_process();

        $cart->reset(true);

// unregister session variables used during checkout
        tep_session_unregister('sendto');
        tep_session_unregister('billto');
        tep_session_unregister('shipping');
        tep_session_unregister('payment');
        tep_session_unregister('comments');

        tep_session_unregister('cart_FEEFIGHTER_ID');

        // tep_redirect(tep_href_link(FILENAME_CHECKOUT_SUCCESS, '', 'SSL'));
    }

    function after_process() {
        return false;
    }

    function output_error() {
        return true;
    }

    function check() {
        if (!isset($this->_check)) {
            $check_query = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_FEEFIGHTER_STATUS'");
            $this->_check = tep_db_num_rows($check_query);
        }
        return $this->_check;
    }

    function install() {
        $check_query = tep_db_query("select orders_status_id from " . TABLE_ORDERS_STATUS . " where orders_status_name = 'Preparing [PayPal Standard]' limit 1");

        if (tep_db_num_rows($check_query) < 1) {
            $status_query = tep_db_query("select max(orders_status_id) as status_id from " . TABLE_ORDERS_STATUS);
            $status = tep_db_fetch_array($status_query);

            $status_id = $status['status_id'] + 1;

            $languages = tep_get_languages();

            foreach ($languages as $lang) {
                tep_db_query("insert into " . TABLE_ORDERS_STATUS . " (orders_status_id, language_id, orders_status_name) values ('" . $status_id . "', '" . $lang['id'] . "', 'Preparing [PayPal Standard]')");
            }

            $flags_query = tep_db_query("describe " . TABLE_ORDERS_STATUS . " public_flag");
            if (tep_db_num_rows($flags_query) == 1) {
                tep_db_query("update " . TABLE_ORDERS_STATUS . " set public_flag = 0 and downloads_flag = 0 where orders_status_id = '" . $status_id . "'");
            }
        } else {
            $check = tep_db_fetch_array($check_query);

            $status_id = $check['orders_status_id'];
        }

        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable FeeFighter Payment Gateway', 'MODULE_PAYMENT_FEEFIGHTER_STATUS', 'False', 'Do you want to accept FeeFighter Payments ?', '6', '3', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('E-Mail Address', 'MODULE_PAYMENT_FEEFIGHTER_ID', '', 'The FeeFighter Payment account email address to accept payments for', '6', '4', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Sort order of display.', 'MODULE_PAYMENT_FEEFIGHTER_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '6', '0', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Gateway Server', 'MODULE_PAYMENT_FEEFIGHTER_GATEWAY_SERVER', 'Live', 'Use the testing (sandbox) or live gateway server for transactions?', '6', '6', 'tep_cfg_select_option(array(\'Live\', \'Sandbox\'), ', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Your FeeFighter Merchant Key', 'MODULE_PAYMENT_FEEFIGHTER_MERCHENT_KEY', '', 'Merchant Key of your FeeFighter payment account.', '6', '4', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Your Samurai FeeFighter Merchant Password', 'MODULE_PAYMENT_FEEFIGHTER_MERCHENT_PASSWORD', '', 'Your Samurai FeeFighter Merchant Account Password .', '6', '4', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Samurai FeeFighter Processor Token', 'MODULE_PAYMENT_FEEFIGHTER_PROCESSOR_TOKEN', '', 'Your Samurai FeeFighter Processor Token.', '6', '4', now())");
    }

    function remove() {
        tep_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
    }

    function keys() {
        return array('MODULE_PAYMENT_FEEFIGHTER_STATUS', 'MODULE_PAYMENT_FEEFIGHTER_ID', 'MODULE_PAYMENT_FEEFIGHTER_SORT_ORDER', 'MODULE_PAYMENT_FEEFIGHTER_GATEWAY_SERVER', 'MODULE_PAYMENT_FEEFIGHTER_MERCHENT_KEY', 'MODULE_PAYMENT_FEEFIGHTER_MERCHENT_PASSWORD', 'MODULE_PAYMENT_FEEFIGHTER_PROCESSOR_TOKEN');
    }

// format prices without currency formatting
    function format_raw($number, $currency_code = '', $currency_value = '') {
        global $currencies, $currency;

        if (empty($currency_code) || !$this->is_set($currency_code)) {
            $currency_code = $currency;
        }

        if (empty($currency_value) || !is_numeric($currency_value)) {
            $currency_value = $currencies->currencies[$currency_code]['value'];
        }

        return number_format(tep_round($number * $currency_value, $currencies->currencies[$currency_code]['decimal_places']), $currencies->currencies[$currency_code]['decimal_places'], '.', '');
    }

    function get_error() {
        global $description;
$error='';
        $error = array('title' => MODULE_PAYMENT_FEEFIGHTER_ERROR_TITLE,
                     'error' => $_SESSION['error_description']);

        return $error;

    }

}

?>
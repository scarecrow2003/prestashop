<?php
/**
 * Created by PhpStorm.
 * User: hougang
 * Date: 4/28/15
 * Time: 10:47 PM
 */

class AuthController extends AuthControllerCore
{
    /**
     * Process submit on an account
     */
    protected function processSubmitAccount()
    {
        Hook::exec('actionBeforeSubmitAccount');
        $this->create_account = true;
        if (Tools::isSubmit('submitAccount'))
            $this->context->smarty->assign('email_create', 1);
        // New Guest customer
        if (!Tools::getValue('is_new_customer', 1) && !Configuration::get('PS_GUEST_CHECKOUT_ENABLED'))
            $this->errors[] = Tools::displayError('You cannot create a guest account.');
        if (!Tools::getValue('is_new_customer', 1))
            $_POST['passwd'] = md5(time()._COOKIE_KEY_);
        if ($guest_email = Tools::getValue('guest_email'))
            $_POST['email'] = $guest_email;
        // Checked the user address in case he changed his email address
        if (Validate::isEmail($email = Tools::getValue('email')) && !empty($email))
            if (Customer::customerExists($email))
                $this->errors[] = Tools::displayError('An account using this email address has already been registered.', false);
        // Preparing customer
        $customer = new Customer();
        $lastnameAddress = Tools::getValue('lastname');
        $firstnameAddress = Tools::getValue('firstname');
        $_POST['firstname'] = Tools::getValue('customer_firstname', $firstnameAddress);
        $_POST['lastname'] = $_POST['firstname'];
        $addresses_types = array('address');
        if (!Configuration::get('PS_ORDER_PROCESS_TYPE') && Configuration::get('PS_GUEST_CHECKOUT_ENABLED') && Tools::getValue('invoice_address'))
            $addresses_types[] = 'address_invoice';

        $error_phone = false;
        if (Configuration::get('PS_ONE_PHONE_AT_LEAST'))
        {
            if (Tools::isSubmit('submitGuestAccount') || !Tools::getValue('is_new_customer'))
            {
                if (!Tools::getValue('phone') && !Tools::getValue('phone_mobile'))
                    $error_phone = true;
            }
            elseif (((Configuration::get('PS_REGISTRATION_PROCESS_TYPE') && Configuration::get('PS_ORDER_PROCESS_TYPE'))
                    || (Configuration::get('PS_ORDER_PROCESS_TYPE') && !Tools::getValue('email_create'))
                    || (Configuration::get('PS_REGISTRATION_PROCESS_TYPE') && Tools::getValue('email_create')))
                && (!Tools::getValue('phone') && !Tools::getValue('phone_mobile')))
                $error_phone = true;
        }

        if ($error_phone)
            $this->errors[] = Tools::displayError('You must register at least one phone number.');

        $this->errors = array_unique(array_merge($this->errors, $customer->validateController()));

        // Check the requires fields which are settings in the BO
        $this->errors = $this->errors + $customer->validateFieldsRequiredDatabase();

        if (!Configuration::get('PS_REGISTRATION_PROCESS_TYPE') && !$this->ajax && !Tools::isSubmit('submitGuestAccount'))
        {
            if (!count($this->errors))
            {
                if (Tools::isSubmit('newsletter'))
                    $this->processCustomerNewsletter($customer);

                $customer->firstname = Tools::ucwords($customer->firstname);
                $customer->birthday = (empty($_POST['years']) ? '' : (int)Tools::getValue('years').'-'.(int)Tools::getValue('months').'-'.(int)Tools::getValue('days'));
                if (!Validate::isBirthDate($customer->birthday))
                    $this->errors[] = Tools::displayError('Invalid date of birth.');

                // New Guest customer
                $customer->is_guest = (Tools::isSubmit('is_new_customer') ? !Tools::getValue('is_new_customer', 1) : 0);
                $customer->active = 1;

                if (!count($this->errors))
                {
                    if ($customer->add())
                    {
                        if (!$customer->is_guest)
                            if (!$this->sendConfirmationMail($customer))
                                $this->errors[] = Tools::displayError('The email cannot be sent.');

                        $this->updateContext($customer);

                        $this->context->cart->update();
                        Hook::exec('actionCustomerAccountAdd', array(
                            '_POST' => $_POST,
                            'newCustomer' => $customer
                        ));
                        if ($this->ajax)
                        {
                            $return = array(
                                'hasError' => !empty($this->errors),
                                'errors' => $this->errors,
                                'isSaved' => true,
                                'id_customer' => (int)$this->context->cookie->id_customer,
                                'id_address_delivery' => $this->context->cart->id_address_delivery,
                                'id_address_invoice' => $this->context->cart->id_address_invoice,
                                'token' => Tools::getToken(false)
                            );
                            $this->ajaxDie(Tools::jsonEncode($return));
                        }

                        if (($back = Tools::getValue('back')) && $back == Tools::secureReferrer($back))
                            Tools::redirect(html_entity_decode($back));
                        // redirection: if cart is not empty : redirection to the cart
                        if (count($this->context->cart->getProducts(true)) > 0)
                            Tools::redirect('index.php?controller=order'.($multi = (int)Tools::getValue('multi-shipping') ? '&multi-shipping='.$multi : ''));
                        // else : redirection to the account
                        else
                            Tools::redirect('index.php?controller='.(($this->authRedirection !== false) ? urlencode($this->authRedirection) : 'my-account'));
                    }
                    else
                        $this->errors[] = Tools::displayError('An error occurred while creating your account.');
                }
            }

        }
        else // if registration type is in one step, we save the address
        {
            $_POST['lastname'] = $lastnameAddress;
            $_POST['firstname'] = $firstnameAddress;
            $post_back = $_POST;
            // Preparing addresses
            foreach ($addresses_types as $addresses_type)
            {
                $$addresses_type = new Address();
                $$addresses_type->id_customer = 1;

                if ($addresses_type == 'address_invoice')
                    foreach ($_POST as $key => &$post)
                        if ($tmp = Tools::getValue($key.'_invoice'))
                            $post = $tmp;

                $this->errors = array_unique(array_merge($this->errors, $$addresses_type->validateController()));
                if ($addresses_type == 'address_invoice')
                    $_POST = $post_back;

                if (!($country = new Country($$addresses_type->id_country)) || !Validate::isLoadedObject($country))
                    $this->errors[] = Tools::displayError('Country cannot be loaded with address->id_country');

                if (!$country->active)
                    $this->errors[] = Tools::displayError('This country is not active.');

                $postcode = $$addresses_type->postcode;
                /* Check zip code format */
                if ($country->zip_code_format && !$country->checkZipCode($postcode))
                    $this->errors[] = sprintf(Tools::displayError('The Zip/Postal code you\'ve entered is invalid. It must follow this format: %s'), str_replace('C', $country->iso_code, str_replace('N', '0', str_replace('L', 'A', $country->zip_code_format))));
                elseif (empty($postcode) && $country->need_zip_code)
                    $this->errors[] = Tools::displayError('A Zip / Postal code is required.');
                elseif ($postcode && !Validate::isPostCode($postcode))
                    $this->errors[] = Tools::displayError('The Zip / Postal code is invalid.');

                if ($country->need_identification_number && (!Tools::getValue('dni') || !Validate::isDniLite(Tools::getValue('dni'))))
                    $this->errors[] = Tools::displayError('The identification number is incorrect or has already been used.');
                elseif (!$country->need_identification_number)
                    $$addresses_type->dni = null;

                if (Tools::isSubmit('submitAccount') || Tools::isSubmit('submitGuestAccount'))
                    if (!($country = new Country($$addresses_type->id_country, Configuration::get('PS_LANG_DEFAULT'))) || !Validate::isLoadedObject($country))
                        $this->errors[] = Tools::displayError('Country is invalid');
                $contains_state = isset($country) && is_object($country) ? (int)$country->contains_states: 0;
                $id_state = isset($$addresses_type) && is_object($$addresses_type) ? (int)$$addresses_type->id_state: 0;
                if ((Tools::isSubmit('submitAccount') || Tools::isSubmit('submitGuestAccount')) && $contains_state && !$id_state)
                    $this->errors[] = Tools::displayError('This country requires you to choose a State.');
            }
        }

        if (!@checkdate(Tools::getValue('months'), Tools::getValue('days'), Tools::getValue('years')) && !(Tools::getValue('months') == '' && Tools::getValue('days') == '' && Tools::getValue('years') == ''))
            $this->errors[] = Tools::displayError('Invalid date of birth');

        if (!count($this->errors))
        {
            if (Customer::customerExists(Tools::getValue('email')))
                $this->errors[] = Tools::displayError('An account using this email address has already been registered. Please enter a valid password or request a new one. ', false);
            if (Tools::isSubmit('newsletter'))
                $this->processCustomerNewsletter($customer);

            /*$customer->birthday = (empty($_POST['years']) ? '' : (int)Tools::getValue('years').'-'.(int)Tools::getValue('months').'-'.(int)Tools::getValue('days'));
            if (!Validate::isBirthDate($customer->birthday))
                $this->errors[] = Tools::displayError('Invalid date of birth');*/

            if (!count($this->errors))
            {
                $customer->active = 1;
                // New Guest customer
                if (Tools::isSubmit('is_new_customer'))
                    $customer->is_guest = !Tools::getValue('is_new_customer', 1);
                else
                    $customer->is_guest = 0;
                if (!$customer->add())
                    $this->errors[] = Tools::displayError('An error occurred while creating your account.');
                else
                {
                    foreach ($addresses_types as $addresses_type)
                    {
                        $$addresses_type->id_customer = (int)$customer->id;
                        if ($addresses_type == 'address_invoice')
                            foreach ($_POST as $key => &$post)
                                if ($tmp = Tools::getValue($key.'_invoice'))
                                    $post = $tmp;

                        $this->errors = array_unique(array_merge($this->errors, $$addresses_type->validateController()));
                        if ($addresses_type == 'address_invoice')
                            $_POST = $post_back;
                        if (!count($this->errors) && (Configuration::get('PS_REGISTRATION_PROCESS_TYPE') || $this->ajax || Tools::isSubmit('submitGuestAccount')) && !$$addresses_type->add())
                            $this->errors[] = Tools::displayError('An error occurred while creating your address.');
                    }
                    if (!count($this->errors))
                    {
                        if (!$customer->is_guest)
                        {
                            $this->context->customer = $customer;
                            $customer->cleanGroups();
                            // we add the guest customer in the default customer group
                            $customer->addGroups(array((int)Configuration::get('PS_CUSTOMER_GROUP')));
                            if (!$this->sendConfirmationMail($customer))
                                $this->errors[] = Tools::displayError('The email cannot be sent.');
                        }
                        else
                        {
                            $customer->cleanGroups();
                            // we add the guest customer in the guest customer group
                            $customer->addGroups(array((int)Configuration::get('PS_GUEST_GROUP')));
                        }
                        $this->updateContext($customer);
                        $this->context->cart->id_address_delivery = (int)Address::getFirstCustomerAddressId((int)$customer->id);
                        $this->context->cart->id_address_invoice = (int)Address::getFirstCustomerAddressId((int)$customer->id);
                        if (isset($address_invoice) && Validate::isLoadedObject($address_invoice))
                            $this->context->cart->id_address_invoice = (int)$address_invoice->id;

                        if ($this->ajax && Configuration::get('PS_ORDER_PROCESS_TYPE'))
                        {
                            $delivery_option = array((int)$this->context->cart->id_address_delivery => (int)$this->context->cart->id_carrier.',');
                            $this->context->cart->setDeliveryOption($delivery_option);
                        }

                        // If a logged guest logs in as a customer, the cart secure key was already set and needs to be updated
                        $this->context->cart->update();

                        // Avoid articles without delivery address on the cart
                        $this->context->cart->autosetProductAddress();

                        Hook::exec('actionCustomerAccountAdd', array(
                            '_POST' => $_POST,
                            'newCustomer' => $customer
                        ));
                        if ($this->ajax)
                        {
                            $return = array(
                                'hasError' => !empty($this->errors),
                                'errors' => $this->errors,
                                'isSaved' => true,
                                'id_customer' => (int)$this->context->cookie->id_customer,
                                'id_address_delivery' => $this->context->cart->id_address_delivery,
                                'id_address_invoice' => $this->context->cart->id_address_invoice,
                                'token' => Tools::getToken(false)
                            );
                            $this->ajaxDie(Tools::jsonEncode($return));
                        }
                        // if registration type is in two steps, we redirect to register address
                        if (!Configuration::get('PS_REGISTRATION_PROCESS_TYPE') && !$this->ajax && !Tools::isSubmit('submitGuestAccount'))
                            Tools::redirect('index.php?controller=address');

                        if (($back = Tools::getValue('back')) && $back == Tools::secureReferrer($back))
                            Tools::redirect(html_entity_decode($back));

                        // redirection: if cart is not empty : redirection to the cart
                        if (count($this->context->cart->getProducts(true)) > 0)
                            Tools::redirect('index.php?controller=order'.($multi = (int)Tools::getValue('multi-shipping') ? '&multi-shipping='.$multi : ''));
                        // else : redirection to the account
                        else
                            Tools::redirect('index.php?controller='.(($this->authRedirection !== false) ? urlencode($this->authRedirection) : 'my-account'));
                    }
                }
            }
        }

        if (count($this->errors))
        {
            //for retro compatibility to display guest account creation form on authentication page
            if (Tools::getValue('submitGuestAccount'))
                $_GET['display_guest_checkout'] = 1;

            if (!Tools::getValue('is_new_customer'))
                unset($_POST['passwd']);
            if ($this->ajax)
            {
                $return = array(
                    'hasError' => !empty($this->errors),
                    'errors' => $this->errors,
                    'isSaved' => false,
                    'id_customer' => 0
                );
                $this->ajaxDie(Tools::jsonEncode($return));
            }
            $this->context->smarty->assign('account_error', $this->errors);
        }
    }
}
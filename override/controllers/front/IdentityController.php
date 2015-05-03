<?php
/**
 * Created by PhpStorm.
 * User: hougang
 * Date: 4/29/15
 * Time: 12:36 AM
 */

class IdentityController extends IdentityControllerCore
{

    public function displayAjax()
    {
        $this->display();
    }

    /**
     * Start forms process
     * @see FrontController::postProcess()
     */
    public function postProcess()
    {
        if (Tools::isSubmit('getList')) {
            $list = $this->getAreaList(Tools::getValue('getList'));
            $return = "<option value='0' selected='selected'>-</option>";
            foreach ($list as $item) {
                $return .= "<option value='".$item['areano']."' >".$item['areaname']."&nbsp;&nbsp;</option>";
            }
            $this->ajaxDie($return);
        } else {

            //$origin_newsletter = (bool)$this->customer->newsletter;

            if ($this->customer->birthday)
                $birthday = explode('-', $this->customer->birthday);
            else
                $birthday = array('-', '-', '-');

            /* Generate years, months and days */
            $this->context->smarty->assign(array(
                'years' => Tools::dateYears(),
                'sl_year' => $birthday[0],
                'months' => Tools::dateMonths(),
                'sl_month' => $birthday[1],
                'days' => Tools::dateDays(),
                'sl_day' => $birthday[2],
                'errors' => $this->errors,
                'genders' => Gender::getGenders(),
            ));

            $provinces = $this->getAreaList(0);
            if ($this->customer->address) {
                $address = Db::getInstance()->getRow("SELECT parentno, arealevel from `"._DB_PREFIX_."prov_city_area` WHERE areano = ".$this->customer->address);
                if ($address['arealevel'] == 3) {
                    $sl_area = $this->customer->address;
                } else if ($address['arealevel'] == 2) {
                    $sl_city = $this->customer->address;
                } else if ($address['arealevel'] == 1) {
                    $sl_province = $this->customer->address;
                }
                if (isset($sl_area)) {
                    $sl_city = $this->getParentAddressNo($sl_area);
                }
                if (isset($sl_city)) {
                    $sl_province = $this->getParentAddressNo($sl_city);
                    $areas = $this->getAreaList($sl_city);
                }
                if (isset($sl_province)) {
                    $cities = $this->getAreaList($sl_province);
                }
            }
            $this->context->smarty->assign(array(
                'areas' => $areas,
                'sl_area' => $sl_area,
                'cities' => $cities,
                'sl_city' => $sl_city,
                'provinces' => $provinces,
                'sl_province' => $sl_province,
            ));

            $cats = $this->getAllUnderCategory(12);
            $interests = explode(',', $this->customer->interest);
            $j = 0;
            for ($i=0; $i<count($interests); $i++) {
                while($j<count($cats)) {
                    if (intval($interests[$i]) == $cats[$j]['id_category']) {
                        $cats[$j]['selected'] = true;
                        $j++;
                        break;
                    }
                    $j++;
                }
            }
            $this->context->smarty->assign("cats", $cats);

            if (Tools::isSubmit('submitIdentity'))
            {
                $email = trim(Tools::getValue('email'));

                if (Tools::getValue('months') != '' && Tools::getValue('days') != '' && Tools::getValue('years') != '')
                    $this->customer->birthday = (int)Tools::getValue('years').'-'.(int)Tools::getValue('months').'-'.(int)Tools::getValue('days');
                elseif (Tools::getValue('months') == '' && Tools::getValue('days') == '' && Tools::getValue('years') == '')
                    $this->customer->birthday = null;
                else
                    $this->errors[] = Tools::displayError('Invalid date of birth.');

                if (Tools::getIsset('old_passwd'))
                    $old_passwd = trim(Tools::getValue('old_passwd'));

                if (!Validate::isEmail($email))
                    $this->errors[] = Tools::displayError('This email address is not valid');
                elseif ($this->customer->email != $email && Customer::customerExists($email, true))
                    $this->errors[] = Tools::displayError('An account using this email address has already been registered.');
                elseif (!Tools::getIsset('old_passwd') || (Tools::encrypt($old_passwd) != $this->context->cookie->passwd))
                    $this->errors[] = Tools::displayError('The password you entered is incorrect.');
                elseif (Tools::getValue('passwd') != Tools::getValue('confirmation'))
                    $this->errors[] = Tools::displayError('The password and confirmation do not match.');
                else
                {
                    $prev_id_default_group = $this->customer->id_default_group;

                    // Merge all errors of this file and of the Object Model
                    $this->errors = array_merge($this->errors, $this->customer->validateController());
                }

                $area = Tools::getValue('area') == '0' ? (Tools::getValue('city') == '0' ? (Tools::getValue('province') == '0' ?  0 : Tools::getValue('province')) : Tools::getValue('city')) : Tools::getValue('area');
                $this->customer->address = $area;

                if (!count($this->errors))
                {
                    $this->customer->id_default_group = (int)$prev_id_default_group;
                    $this->customer->firstname = Tools::ucwords($this->customer->firstname);

                    if (Configuration::get('PS_B2B_ENABLE'))
                    {
                        $this->customer->website = Tools::getValue('website'); // force update of website, even if box is empty, this allows user to remove the website
                        $this->customer->company = Tools::getValue('company');
                    }

                    /*if (!Tools::getIsset('newsletter'))
                        $this->customer->newsletter = 0;
                    elseif (!$origin_newsletter && Tools::getIsset('newsletter'))
                        if ($module_newsletter = Module::getInstanceByName('blocknewsletter'))
                            if ($module_newsletter->active)
                                $module_newsletter->confirmSubscription($this->customer->email);*/

                    /*if (!Tools::getIsset('optin'))
                        $this->customer->optin = 0;*/
                    if (Tools::getValue('passwd'))
                        $this->context->cookie->passwd = $this->customer->passwd;
                    if ($this->customer->update())
                    {
                        $this->context->cookie->customer_firstname = $this->customer->firstname;
                        $this->context->cookie->customer_lastname = $this->customer->firstname;
                        $this->context->smarty->assign('confirmation', 1);
                    }
                    else
                        $this->errors[] = Tools::displayError('The information cannot be updated.');
                }
            }
            else
                $_POST = array_map('stripslashes', $this->customer->getFields());

            return $this->customer;
        }
    }
    /**
     * Assign template vars related to page content
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        parent::initContent();

        // Call a hook to display more information
        $this->context->smarty->assign(array(
            'HOOK_CUSTOMER_IDENTITY_FORM' => Hook::exec('displayCustomerIdentityForm'),
        ));

       /* $newsletter = Configuration::get('PS_CUSTOMER_NWSL') || (Module::isInstalled('blocknewsletter') && Module::getInstanceByName('blocknewsletter')->active);
        $this->context->smarty->assign('newsletter', $newsletter);*/
//        $this->context->smarty->assign('optin', (bool)Configuration::get('PS_CUSTOMER_OPTIN'));

        $this->context->smarty->assign('field_required', $this->context->customer->validateFieldsRequiredDatabase());

        $this->setTemplate(_PS_THEME_DIR_.'identity.tpl');
    }

    private function getAreaList($parentno) {
        $areas = Db::getInstance()->executeS("SELECT areano, areaname from `"._DB_PREFIX_."prov_city_area` WHERE parentno = ".$parentno);
        return $areas;
    }

    private function getParentAddressNo($child) {
        $parent = Db::getInstance()->getValue("SELECT parentno from `"._DB_PREFIX_."prov_city_area` WHERE areano = ".$child);
        return $parent;
    }

    private function getAllUnderCategory($cat) {
        $category = new Category();
        return $category->getChildren($cat, $this->context->language->id);
    }

}

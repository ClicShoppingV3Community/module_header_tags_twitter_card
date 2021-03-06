<?php
  /**
   *
   * @copyright 2008 - https://www.clicshopping.org
   * @Brand : ClicShopping(Tm) at Inpi all right Reserved
   * @Licence GPL 2 & MIT
   * @licence MIT - Portion of osCommerce 2.4
   * @Info : https://www.clicshopping.org/forum/trademark/
   *
   */

  namespace ClicShopping\Apps\Catalog\Products\Module\HeaderTags;

  use ClicShopping\OM\Registry;
  use ClicShopping\OM\HTTP;
  use ClicShopping\OM\HTML;
  use ClicShopping\OM\CLICSHOPPING;

  use ClicShopping\Apps\Catalog\Products\Products as ProductsApp;

  class TwitterCard extends \ClicShopping\OM\Modules\HeaderTagsAbstract
  {

    protected $lang;
    protected $app;
    public string $group;

    protected function init()
    {
      if (!Registry::exists('Products')) {
        Registry::set('Products', new ProductsApp());
      }

      $this->app = Registry::get('Products');
      $this->lang = Registry::get('Language');
      $this->group = 'footer_scripts'; // could be header_tags or footer_scripts

      $this->app->loadDefinitions('Module/HeaderTags/twitter_card');

      $this->title = $this->app->getDef('module_header_tags_twitter_card_title');
      $this->description = $this->app->getDef('module_header_tags_twitter_card_description');

      if (\defined('MODULE_HEADER_TAGS_PRODUCT_TWITTER_CARD_STATUS')) {
        $this->sort_order = (int)MODULE_HEADER_TAGS_PRODUCT_TWITTER_CARD_SORT_ORDER;
        $this->enabled = (MODULE_HEADER_TAGS_PRODUCT_TWITTER_CARD_STATUS == 'True');
      }
    }

    public function isEnabled()
    {
      return $this->enabled;
    }

    public function getOutput()
    {
      $CLICSHOPPING_Template = Registry::get('Template');
      $CLICSHOPPING_Currencies = Registry::get('Currencies');
      $CLICSHOPPING_Customer = Registry::get('Customer');
      $CLICSHOPPING_ProductsCommon = Registry::get('ProductsCommon');
      $CLICSHOPPING_Tax = Registry::get('Tax');

      if (!\defined('CLICSHOPPING_APP_CATALOG_PRODUCTS_PD_STATUS') || CLICSHOPPING_APP_CATALOG_PRODUCTS_PD_STATUS == 'False') {
        return false;
      }

      if (isset($_GET['Products']) && isset($_GET['Description'])) {
        if ($CLICSHOPPING_Customer->getCustomersGroupID() == 0) {

          $Qproduct = $this->app->db->prepare('select p.products_price
                                               from :table_products p,
                                                   :table_products_to_categories p2c,
                                                   :table_categories c
                                               where p.products_id = :products_id
                                               and p.products_status = 1
                                               and p.products_view = 1
                                               and p.products_id = p2c.products_id
                                               and p2c.categories_id = c.categories_id
                                               and c.status = 1
                                             ');
          $Qproduct->bindInt(':products_id', $CLICSHOPPING_ProductsCommon->getId());
          $Qproduct->execute();

          if ($Qproduct->fetch() !== false) {

            $data = ['card' => 'product',
              'title' => $CLICSHOPPING_ProductsCommon->getProductsName(),
              'domain' => HTTP::typeUrlDomain()
            ];

            if (!\is_null(MODULE_HEADER_TAGS_PRODUCT_TWITTER_CARD_SITE_ID)) {
              $data['site'] = MODULE_HEADER_TAGS_PRODUCT_TWITTER_CARD_SITE_ID;
            }

            if (!\is_null(MODULE_HEADER_TAGS_PRODUCT_TWITTER_CARD_USER_ID)) {
              $data['creator'] = MODULE_HEADER_TAGS_PRODUCT_TWITTER_CARD_USER_ID;
            }

            $product_description = substr(trim(preg_replace('/\s\s+/', ' ', strip_tags($CLICSHOPPING_ProductsCommon->getProductsDescription()))), 0, 197);

            if (strlen($product_description) == 197) {
              $product_description .= ' ..';
            }

            $products_image = $CLICSHOPPING_ProductsCommon->getProductsImage();

            $Qimage = $this->app->db->get('products_images', 'image', ['products_id' => $CLICSHOPPING_ProductsCommon->getID()], 'sort_order', 1);

            if ($Qimage->fetch() !== false) {
              $products_image = $Qimage->value('image');
            }

            if ($new_price = $CLICSHOPPING_ProductsCommon->getProductsSpecialPrice()) {
              $products_price = $CLICSHOPPING_Currencies->displayPrice($new_price, $CLICSHOPPING_Tax->getTaxRate($CLICSHOPPING_ProductsCommon->getProductsTaxClassId()));
            } else {
              $products_price = $CLICSHOPPING_Currencies->displayPrice($Qproduct->value('products_price'), $CLICSHOPPING_Tax->getTaxRate($CLICSHOPPING_ProductsCommon->getProductsTaxClassId()));
            }

            $data['description'] = $product_description;

            $data['image:src'] = CLICSHOPPING::link($CLICSHOPPING_Template->getDirectoryTemplateImages() . $products_image, null);

            $data['data1'] = html_entity_decode($products_price);
            $data['label1'] = $_SESSION['currency'];

            if ($Qproduct->value('products_date_available') > date('Y-m-d H:i:s')) {
              $data['data2'] = CLICSHOPPING::getDef('module_header_tags_product_twitter_card_text_pre_order');
              $data['label2'] = DateTime::toShort($CLICSHOPPING_ProductsCommon->getProductsDateAvailable());

            } elseif ($CLICSHOPPING_ProductsCommon->getProductsStock() > 0) {
              $data['data2'] = CLICSHOPPING::getDef('module_header_tags_product_twitter_card_text_in_stock');
              $data['label2'] = CLICSHOPPING::getDef('module_header_tags_product_twitter_card_text_buy_now');
            } else {
              $data['data2'] = CLICSHOPPING::getDef('module_header_tags_product_twitter_card_text_out_of_stock');
              $data['label2'] = CLICSHOPPING::getDef('module_header_tags_product_twitter_card_text_contact_us');
            }

            $result = '<!-- Start Twitter Card -->' . "\n";
            $result .= '<meta name="twitter:card" content="' . MODULE_HEADER_TAGS_PRODUCT_TWITTER_CARD_TYPE . '" />' . "\n";

            foreach ($data as $key => $value) {
              $result .= '<meta name="twitter:' . HTML::outputProtected($key) . '"  content="' . HTML::outputProtected($value) . '" />' . "\n";
            }

            $result .= '<!-- end Twitter Card -->' . "\n";

            $display_result = $CLICSHOPPING_Template->addBlock($result, $this->group);

            $output =
              <<<EOD
{$display_result}
EOD;

            return $output;
          }
        }
      }
    }

    public function Install()
    {
      $this->app->db->save('configuration', [
          'configuration_title' => 'Do you want activate this module ?',
          'configuration_key' => 'MODULE_HEADER_TAGS_PRODUCT_TWITTER_CARD_STATUS',
          'configuration_value' => 'True',
          'configuration_description' => 'Do you want activate this module ?',
          'configuration_group_id' => '6',
          'sort_order' => '1',
          'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
          'date_added' => 'now()'
        ]
      );


      $this->app->db->save('configuration', [
          'configuration_title' => 'Twitter Author @username',
          'configuration_key' => 'MODULE_HEADER_TAGS_PRODUCT_TWITTER_CARD_USER_ID',
          'configuration_value' => '',
          'configuration_description' => 'Your @username at Twitter',
          'configuration_group_id' => '6',
          'sort_order' => '2',
          'set_function' => '',
          'date_added' => 'now()'
        ]
      );


      $this->app->db->save('configuration', [
          'configuration_title' => 'Twitter Shop @username',
          'configuration_key' => 'MODULE_HEADER_TAGS_PRODUCT_TWITTER_CARD_SITE_ID',
          'configuration_value' => '',
          'configuration_description' => 'Your shops @username at Twitter (or leave blank if it is the same as your @username above)',
          'configuration_group_id' => '6',
          'sort_order' => '1',
          'set_function' => '',
          'date_added' => 'now()'
        ]
      );

      $this->app->db->save('configuration', [
          'configuration_title' => 'Choose Twitter Card Type ?',
          'configuration_key' => 'MODULE_HEADER_TAGS_PRODUCT_TWITTER_CARD_TYPE',
          'configuration_value' => 'summary_large_image',
          'configuration_description' => '<strong>Note :</strong><br /><br />Concerne uniquement l\'envoie via l\'administration des twitts<br />- Twitter_card affiche une card selon l\'aspect Twitter Card<br />- twitter_clicshopping affiche une card selon le système media twitter',
          'configuration_group_id' => '6',
          'sort_order' => '1',
          'set_function' => 'clic_cfg_set_boolean_value(array(\'summary\', \'summary_large_image\'))',
          'date_added' => 'now()'
        ]
      );

      $this->app->db->save('configuration', [
          'configuration_title' => 'Choose Twitter Card Type sent via Administration?',
          'configuration_key' => 'MODULE_HEADER_TAGS_PRODUCT_TWITTER_CARD_TYPE_ADMINISTRATION',
          'configuration_value' => 'twitter_clicshopping',
          'configuration_description' => '<strong>Note :</strong><br /><br />Just sent via administration if available<br />- Twitter_card dusplay a card with Twitter Card aspect<br />- Twitter_clicshopping display a card with media twitter system aspect',
          'configuration_group_id' => '6',
          'sort_order' => '1',
          'set_function' => 'clic_cfg_set_boolean_value(array(\'twitter_card\', \'twitter_clicshopping\'))',
          'date_added' => 'now()'
        ]
      );


      $this->app->db->save('configuration', [
          'configuration_title' => 'Sort Order',
          'configuration_key' => 'MODULE_HEADER_TAGS_PRODUCT_TWITTER_CARD_SORT_ORDER',
          'configuration_value' => '200',
          'configuration_description' => 'Sort order of display. Lowest is displayed first.',
          'configuration_group_id' => '6',
          'sort_order' => '205',
          'set_function' => '',
          'date_added' => 'now()'
        ]
      );
    }

    public function keys()
    {
      return ['MODULE_HEADER_TAGS_PRODUCT_TWITTER_CARD_STATUS',
        'MODULE_HEADER_TAGS_PRODUCT_TWITTER_CARD_USER_ID',
        'MODULE_HEADER_TAGS_PRODUCT_TWITTER_CARD_SITE_ID',
        'MODULE_HEADER_TAGS_PRODUCT_TWITTER_CARD_TYPE',
        'MODULE_HEADER_TAGS_PRODUCT_TWITTER_CARD_TYPE_ADMINISTRATION',
        'MODULE_HEADER_TAGS_PRODUCT_TWITTER_CARD_SORT_ORDER'
      ];
    }
  }

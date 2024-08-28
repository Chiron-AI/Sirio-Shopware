<?php declare(strict_types=1);

namespace Chiron\Sirio\Services;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Twig\Environment;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartRuleLoader;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Shopware\Core\Content\Category\SalesChannel\AbstractCategoryRoute;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Kernel;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RequestStack;
use Shopware\Core\Framework\Store\Authentication\LocaleProvider;
use Shopware\Storefront\Page\Checkout\Finish\CheckoutFinishPage;

class SirioProfilingRenderer implements SirioProfilingRendererInterface
{
   

    /**
     * @var Environment
     */
    private $twig;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var array
     */
    private $variables = [];

    private $page;

    /**
     * @var SalesChannelContextServiceInterface
     */
    private $salesChannelContextService;

    private $twigContext;
    
    private $script;
    
    private LocaleProvider $localeProvider;

    private $framework;

    private $framework_version;

    const FRAMEWORK = 'shopware';

    /**
     * @var array
     */
    private $sirioProfiling = [];

    public function __construct(
        Connection $connection,
        SalesChannelContextServiceInterface $salesChannelContextService,
        RequestStack $requestStack,
        LocaleProvider $localeProvider,
        CartService $cartService,
        CartRuleLoader $cartRuleLoader,
        AbstractCategoryRoute $cmsPageRoute,
        SirioProfilingModules $modules
    ) {
        $this->connection = $connection;
        $this->salesChannelContextService = $salesChannelContextService;
        $this->requestStack = $requestStack;
        $this->localeProvider = $localeProvider;
        $this->cartService = $cartService;
        $this->cartRuleLoader = $cartRuleLoader;
        $this->cmsPageRoute = $cmsPageRoute;
        $this->modules = $modules;

        $this->framework = self::FRAMEWORK;

        $context = Context::createDefaultContext();
        $this->framework_version = $context->getVersionId();

        $this->script = "sirioCustomObject = {};";
    }

    public function renderSirioProfiling(string $route): SirioProfilingRendererInterface
    {   
        $this->sirioProfiling = [$route=>''];
        try {
            $this->getSirioEvent($route);
            if (json_last_error() > 0) {
                throw new \Exception(json_last_error_msg(), 1620985321);
            }
        } catch (\Exception $e) {
            print_r($e->getMessage());
        }
       
        return $this;
    }

    public function getVariables(string $route): array
    {
        if(!isset($this->variables[$route])){
            $this->variables[$route] = [];
        }
        return $this->variables[$route];
    }

    public function setVariables($route, $variables): SirioProfilingRendererInterface
    {
        $this->variables[$route] = $variables;

        return $this;
    }

    public function getSirioProfiling($route): ?string
    {
        return $this->sirioProfiling[$route] ?? null;
    }

    public function getSirioEvent($route) {
        
		$this->getHeaders();
        $this->getCurrency();
        $this->getLocale();
        $this->getFramework();
        $this->getCustomer();
		
        if($route == 'frontend.home.page') {
			$this->appendHomeJS($route);
		}
		else if ($route == 'frontend.detail.page') {
			$this->appendProductJS($route);
		}
		else if ($route == 'frontend.navigation.page') {
			$this->appendProductCategoryJS($route);
		}
		else if ($route == 'frontend.search.page') {
			$this->appendProductSearchJS($route);
		}
        else if ($route == 'frontend.checkout.finish.page') {
			$this->appendCheckoutSuccessJS($route);
		}
        else if (stristr($route, 'frontend.checkout')) {
			$this->appendCheckoutJS($route);
		}
		else{
			$this->appendDefaultJS($route);
		}
		/*else if ($route == 'failure') {
			$this->appendCheckoutFailureJS($route);
		}*/
	
        return;	
	}
	
	private function appendDefaultJS($route) {
		$this->sirioProfiling[$route] = '<script type="text/javascript">
                     //<![CDATA[
                     '.$this->script.'
                     //]]>
                 </script>';
    }

    private function appendHomeJS($route) {
        $this->sirioProfiling[$route] = '<script type="text/javascript">
                     //<![CDATA[
                     '.$this->script.'
                     sirioCustomObject.pageType = "home";
                     //]]>
                 </script>';
    }

    private function appendProductJS($route) {


        try{
            $page = $this->getVariables($route)['page'];
            if($page==null){
                return;
            }
            if(get_class_methods($page)==null){
                return;
            }
            if(in_array("getProduct",get_class_methods($page))){
                $current_product = $page->getProduct();
            }
            else{
                return;
            }
        }
        catch (\Exception $e){
            return;
        }
        $image = "";
        if($current_product->getMedia() != null && $current_product->getMedia()->first() != null ){
            $image = $current_product->getMedia()->first()->getMedia()->getUrl();
        }

        $breadcrumbs=null;
        //TODO fix special price and breadcrumbs
        $this->sirioProfiling[$route] = '<script type="text/javascript">
                     //<![CDATA[
                     '.$this->script.'
                     sirioCustomObject.productDetails = {"sku":"'.$current_product->getProductNumber().'","name":"'.$current_product->getName().'","image":"'.$image.'","description":"'.$this->cleanTextProduct($current_product->getDescription()).'","qty":"'.$current_product->getAvailableStock().'","breadcrumbs":"' . addslashes(stripslashes(json_encode(array($breadcrumbs)))) . '","price":"'.$current_product->getPrice()->first()->getGross().'","special_price":"'.$current_product->getPrice()->first()->getGross().'"};
                     sirioCustomObject.pageType = "product";
                     //]]>
                 </script>';
    }

    private function appendProductCategoryJS($route) {

        try{
            $page = $this->getVariables($route)['page'];
            if($page==null){
                return;
            }
            if(get_class_methods($page)==null){
                return;
            }
            if(in_array("getNavigationId",get_class_methods($page))){
                $navigationId = $page->getNavigationId();
            }
            else{
                return;
            }
        }
        catch (\Exception $e){
            return;
        }
        $current_category = $this->cmsPageRoute
            ->load($navigationId, $this->requestStack->getCurrentRequest(), $this->getSalesChannelContext())
            ->getCategory();
        $image = "";
        if($current_category->getMedia() != null){
            $image = $current_category->getMedia()->getUrl();
        }
        $limit = 24;//
        $page = $this->getParam('p')?$this->getParam('p'):1;
        $products_count = $limit;
        $max_product_count = 0;
        //
        $pageCms = $this->getVariables($route)['page']->getCmsPage();

        foreach($pageCms->getSections()->getElements() as $element){

            foreach($element->getBlocks()->getElements() as $elementNested){
                if($elementNested->getType()=='product-listing'){
                    foreach($elementNested->getSlots() as $elementSlot){
                        $max_product_count = $elementSlot->getData()->getListing()->getTotal();
                        $limit = $elementSlot->getData()->getListing()->getLimit();
                    }

                }

            }

        }

        if($max_product_count % $limit > 0){
            $pages = (int)($max_product_count / $limit) + 1 ;
        }
        else{
            $pages = $max_product_count / $limit ;
        }
        if($page == $pages){
            $products_count = $max_product_count % $limit;
        }

        $this->sirioProfiling[$route] = '<script type="text/javascript">
                     //<![CDATA[
                     '.$this->script.'
                     sirioCustomObject.categoryDetails = {"id":"'.$current_category->getId().'","name":"'.$current_category->getName().'","image":"'.$image.'","description":"'.$this->cleanTextCategory($current_category->getDescription()).'"};
                     sirioCustomObject.pageType = "category";
                     sirioCustomObject.numProducts = '.$products_count.';
                     sirioCustomObject.pages = '.$pages.';
                     sirioCustomObject.currentPage = '.$page.';
                     //]]>
                 </script>';
    }

    private function appendProductSearchJS($route) {
        $limit = 24;//
        $page = $this->getParam('p')?$this->getParam('p'):1;
        $products_count = $limit;
        $max_product_count = 0;
        //
        $pageCms = $this->getVariables($route)['page'];
        try{
            $max_product_count = $pageCms->getListing()->getTotal();
            $limit = $pageCms->getListing()->getLimit();
        }
        catch (\Exception $e){
            $pageCms = $pageCms->getCmsPage();
            foreach($pageCms->getSections()->getElements() as $element){
                foreach($element->getBlocks()->getElements() as $elementNested){
                    if($elementNested->getType()=='product-listing'){
                        foreach($elementNested->getSlots() as $elementSlot){
                            $max_product_count = $elementSlot->getData()->getListing()->getTotal();
                            $limit = $elementSlot->getData()->getListing()->getLimit();
                        }
                    }
                }
            }
        }



        if ($pageCms->getSearchTerm()) {//getSearchTerm
            $this->script.='sirioCustomObject.query = "' . $pageCms->getSearchTerm() . '";';
        }

        if($max_product_count % $limit > 0){
            $pages = (int)($max_product_count / $limit) + 1 ;
        }
        else{
            $pages = $max_product_count / $limit ;
        }
        if($page == $pages){
            $products_count = $max_product_count % $limit;
        }

        $this->sirioProfiling[$route] = '<script type="text/javascript">
                     //<![CDATA[
                     '.$this->script.'
                     sirioCustomObject.pageType = "search";
                     sirioCustomObject.numProducts = '.$products_count.';
                     sirioCustomObject.pages = '.$pages.';
                     sirioCustomObject.currentPage = '.$page.';
                     //]]>
                 </script>';
    }


    private function appendCheckoutJS($route) {

        $this->setSirioCart($route);
        $this->sirioProfiling[$route] = '<script type="text/javascript">
                     //<![CDATA[
                     '.$this->script.'
                     sirioCustomObject.pageType = "checkout";
                     //]]>
                 </script>';
    }

    private function appendCheckoutSuccessJS($route) {
        $this->setSirioCart($route);
        $this->sirioProfiling[$route] =  '<script type="text/javascript">
                     //<![CDATA[
                     '.$this->script.'
                     sirioCustomObject.pageType = "checkout_success";
                     //]]>
                 </script>';
        if(isset($_COOKIE['sirio_cart'])){
            unset($_COOKIE['sirio_cart']);
        }

    }

    private function appendCheckoutFailureJS($route) {
        if(isset($_COOKIE['sirio_cart'])){
            setcookie('sirio_cart', "", 1);
        }
        $this->sirioProfiling[$route] =  '<script type="text/javascript">
                     //<![CDATA[
                     '.$this->script.'
                     sirioCustomObject.pageType = "checkout_failure";
                     //]]>
                 </script>';
    }


    protected function getCart()
    {
        $salesChannelContext = $this->getSalesChannelContext();
        $cart = $this->cartService->getCart($salesChannelContext->getToken(), $salesChannelContext);

        return $cart;
    }


    /**
     * @param $items
     * @return array
     */
    protected function makeItemArray($cart)
    {
        $itemArray = [];
        //DISCOUNT_LINE_ITEM
        $discount=[];
        $discountCodes=[];
        foreach ($cart->getLineItems()->filterType(LineItem::PROMOTION_LINE_ITEM_TYPE)->getFlat() as $item) {
            if(isset($item->getPayload()['composition']) && is_array($item->getPayload()['composition'])){
                $discount = $item->getPayload()['composition'];
            }
            if(isset($item->getPayload()['code'])){
                $discountCodes[]=$item->getPayload()['code'];
            }


        }
        foreach ($cart->getLineItems()->filterType(LineItem::PRODUCT_LINE_ITEM_TYPE)->getFlat() as $item) {
            if (
                empty($item->getPayload())
                || !isset($item->getPayload()['productNumber'])
                || empty($item->getPayload()['productNumber'])
            ) {
                continue;
            }
            //
            $discountUnitPrice = 0.0;
            foreach($discount as $discountItem){
                if($item->getId() == $discountItem['id']){
                    $discountUnitPrice = (float)$discountItem['discount']/$discountItem['quantity'];
                }
            }
            $data = [
                'item_id' => $item->getId(),
                'price' => (float)$item->getPrice()->getUnitPrice()-$discountUnitPrice,
                'price_original' => $item->getPrice()->getUnitPrice(),
                "sku"=>$item->getPayload()['productNumber'],
                "product_options" => $item->getPayload()['options'],
                "qty"=>$item->getQuantity(),
                "name"=>$item->getLabel(),

            ];
            $itemArray[] = $data;

        }
        return $itemArray;
    }

    /**
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function setSirioCart($eventName) {

        try {

            $cart = $this->getCart();
            $orderId="";

            //Shopware\Storefront\Page\Checkout\Finish\CheckoutFinishPage
            if(!empty($this->getVariables($eventName)['page']) && method_exists($this->getVariables($eventName)['page'],"getOrder")){
                $order = $this->getVariables($eventName)['page']->getOrder();
                $orderId = $order->getOrderNumber();
            }

            if($cart->getLineItems()->count() > 0){

                $itemArray = $this->makeItemArray($cart);

                $discountCodes=[];
                foreach ($cart->getLineItems()->filterType(LineItem::PROMOTION_LINE_ITEM_TYPE)->getFlat() as $item) {
                    if(isset($item->getPayload()['code'])){
                        $discountCodes[]=$item->getPayload()['code'];
                    }
                }
                $coupon = implode(",", $discountCodes);
                $shipping = $cart->getShippingCosts()->getTotalPrice();
                $total = $cart->getPrice()->getTotalPrice();

                $products = array();
                $subtotal = $discount = 0.0;
                foreach($itemArray as $item){

                    $products[] = array(
                        "item_id"=>$item['item_id'],
                        "sku"=>$item['sku'] ,
                        "product_options"=>$item['product_options'],
                        "price"=>$item['price'],
                        "qty"=>$item['qty'],
                        "name"=>$item['name'],
                        "discount_amount"=>round(($item['price_original']-$item['price']),2)
                    );
                    $subtotal+=round($item['price_original']*$item['qty'],2);
                    $discount+=round(($item['price_original']-$item['price'])*$item['qty'],2);
                }

                $cart_full = '{"order_id":"'.$orderId.'","action_type":"'.$this->getActionType($eventName).'","cart_total":'.$total.',"cart_subtotal":'.$subtotal.',"shipping":'.$shipping.',"coupon_code":"'.$coupon.'","discount_amount":'.$discount.',"cart_products":'.json_encode($products).'}';
                setcookie('sirio_cart', base64_encode($cart_full), time() + (86400 * 30), "/");
                return base64_encode($cart_full);
            }
            else if(!empty($orderId)){
                $cart_full = '{"order_id":"'.$orderId.'"}';
                setcookie('sirio_cart', base64_encode($cart_full), time() + (86400 * 30), "/");
                return base64_encode($cart_full);
            }
            if(isset($_COOKIE['sirio_cart'])){
                setcookie('sirio_cart', "", 1);
            }




        } catch (\Exception $exception) {
            throw new \Exception($exception->getMessage());
        }
        return;
    }

    protected function getActionType($eventName){
        $actionType = "";
        switch($eventName) {
            case "CustomerLoginEvent":
                $actionType = "login";
                break;
            case "AfterLineItemAddedEvent":
                $actionType = "addtocart";
                break;
            case "AfterLineItemRemovedEvent":
                $actionType = "removefromcart";
                break;
            case "AfterLineItemQuantityChangedEvent":
                $actionType = "updatecart";
                break;
            case "frontend.checkout.cart.page":
                $actionType = "viewcart";
                break;
            case "frontend.checkout.info":
                $actionType = "viewcart";
                break;
            /*case "":
                $actionType = "changeqty";
                break;  */
            /*case "":
                $actionType = "applycoupon";
                break; */
            default:
                //$actionType = $eventName;
                break;
        }

        if(!$actionType){
            if (!isset($_SERVER['HTTP_REFERER'])) {// !$actionType //!$this->redirect->getRefererUrl()
                $actionType = "externallink";
            }
        }
        return $actionType;
    }


    protected function getHeaders(){
        $header_request = getallheaders();
        $header_response = headers_list();
        $header_response_status_code = http_response_code();

        $header_response_filtered = array();

        foreach ($header_response as $response) {
            $explode_pos = strpos($response,':');
            $key = substr($response, 0, $explode_pos);
            if($key !== 'Link'){
                $value = substr($response, $explode_pos);
                $header_response_filtered[] = array($key, $value);
            }
        }

        $headers = array(
            'request'=>array(
                'Accept-Encoding'=>isset($header_request['Accept-Encoding'])?$header_request['Accept-Encoding']:"",
                'Accept-Language'=>isset($header_request['Accept-Encoding'])?$header_request['Accept-Language']:"",
                'Cookie'=>isset($header_request['Cookie'])?$header_request['Cookie']:""
            ),
            'response'=>array(
                $header_response_filtered,
                'status_code'=>$header_response_status_code
            )
        );

        $this->script .= 'sirioCustomObject.headers = '.json_encode($headers).';';


    }


    protected function getCurrency(){
        $salesChannelContext = $this->getSalesChannelContext();
        $this->script.='sirioCustomObject.currency = \''.$salesChannelContext->getCurrency()->getIsoCode().'\';';
    }


    protected function getLocale(){
        $locale = $this->localeProvider->getLocaleFromContext($this->getSalesChannelContext()->getContext());
        $locale = strstr($locale, '-', true);
        $this->script.='sirioCustomObject.locale = \''.$locale.'\';';
    }

    protected function getFramework(){
        $this->script.='sirioCustomObject.framework = \''.$this->framework.'\';';
        $this->script.='sirioCustomObject.frameworkVersion = \''.$this->framework_version.'\';';
        if($this->modules->getDebugMode()){
            $this->script.='sirioCustomObject.debugMode = \''.($this->modules->getDebugMode()?"1":"0").'\';';
        }

    }

    protected function getCustomer(){
        $customerData=array();
        $customer = $this->getSalesChannelContext()->getCustomer();

        if(null !== $customer){
            $customerData['customer_id']=$customer->getId();
        }
        if(!empty($customerData)){
            $this->script.='sirioCustomObject.customer= \''.json_encode($customerData).'\';';
        }

    }


    public function getParam(string $param)
    {
        $parameters = array_merge(
            $this->requestStack->getCurrentRequest()->request->all(),
            $this->requestStack->getCurrentRequest()->get('_route_params')
        );

        return @$parameters[$param];
    }


    protected function cleanTextProduct($string){
        if(!$string){
            return $string;
        }
        return  preg_replace('/\R/', '',
            str_replace("<br/>","",
                addslashes(
                    str_replace("'\n''","",
                        str_replace("'\r''","",
                            str_replace("'\t''","",
                                strip_tags(
                                    trim($string))))))));
    }

    protected function cleanTextCategory($string){
        if(!$string){
            return $string;
        }
        return  preg_replace('/\R/', '',
            str_replace("<br/>","",
                addslashes(
                    str_replace("'\n''","",
                        str_replace("'\r''","",
                            str_replace("'\t''","",
                                strip_tags(
                                    trim(($string)))))))));
    }

    private function getSalesChannelContext(): SalesChannelContext
    {
        if (
            !$this->twigContext
            || !$this->twigContext instanceof SalesChannelContext
        ) {
            $masterRequest = $this->requestStack->getMasterRequest();
            $salesChannelId = $masterRequest
                ->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID);
            $contextToken = $masterRequest
                ->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN);
            $languageId = $masterRequest
                ->headers->get(PlatformRequest::HEADER_LANGUAGE_ID);
            if ((float)substr(Kernel::SHOPWARE_FALLBACK_VERSION, 0,3) >= 6.4 ) {
                $parameters = new \Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters(
                    $salesChannelId,
                    $contextToken,
                    $languageId
                );
                $this->twigContext = $this->salesChannelContextService->get($parameters);
            } else {
                $this->twigContext = $this->salesChannelContextService->get(
                    $salesChannelId,
                    $contextToken,
                    $languageId
                );
            }
        }

        return $this->twigContext;
    }


}

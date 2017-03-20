<?php

class onWidgetsPageIsAllowed extends cmsAction {

    private $denied_type;
    private $country;

    public function run($allowed){

        $matched_pages = $this->cms_core->loadMatchedPages()->getMatchedPages();

        if (!$matched_pages) { return $allowed; }

        foreach ($matched_pages as $page) {

            // проверяем доступ по группе
            // если хотя бы для одной из страниц запрещено, закрываем доступ
            if (!empty($page['groups']['view']) && !$this->cms_user->isInGroups($page['groups']['view'])) {
                $allowed = false;
            }
            if (!empty($page['groups']['hide']) && $this->cms_user->isInGroups($page['groups']['hide'])) {
                $allowed = false;
            }

            // прерываем перебор после первого запрета
            if(!$allowed){ $this->denied_type = 'group'; break; }

            // проверяем доступ по странам
            if ((!empty($page['countries']['view']) && $page['countries']['view'] != array(0)) || !empty($page['countries']['hide'])) {

                $this->detectUserCountry();

                if (!$this->isInCountry($page['countries']['view'])) {
                    $allowed = false;
                }
                if ($this->isInCountry($page['countries']['hide'])) {
                    $allowed = false;
                }

            }

            if(!$allowed){ $this->denied_type = 'country'; break; }

        }

        if(!$allowed){
            $this->displayAccessError($page);
        }

        return $allowed;

    }

    private function displayAccessError($page) {

        header("HTTP/1.0 403 Forbidden");
        header("HTTP/1.1 403 Forbidden");
        header("Status: 403 Forbidden");

        if($page['controller']){

            if ($page['controller'] === 'content'){

                $ctypes = cmsCore::getModel('content')->getContentTypes();

                foreach ($ctypes as $_ctype) {
                    if(strpos($page['name'], $_ctype['name'].'.') === 0){
                        $page['title_subject'] = $_ctype['title'];
                        break;
                    }
                }

            }

            cmsCore::loadControllerLanguage($page['controller']);

        }

        $page['title'] = !empty($page['title']) ?
                                $page['title'] :
                                sprintf(constant($page['title_const']), $page['title_subject']);

        $this->cms_template->setContext($this);

        $this->cms_template->setPageTitle(LANG_ACCESS_DENIED, $page['title']);
        $this->cms_template->addBreadcrumb($page['title']);

        $this->cms_template->addOutput($this->cms_template->render('access_error', array(
            'page'        => $page,
            'hint'        => string_lang('LANG_ACCESS_'.$this->denied_type.'_HINT'),
            'denied_type' => $this->denied_type
        )));

    }

    private function detectUserCountry() {

        if($this->country !== null){ return $this; }

        $country = cmsUser::sessionGet('cached_geo');

        if(!$country){

            $json = file_get_contents_from_url('http://freegeoip.net/json/'.cmsUser::getIp());

            if($json){
                $data = json_decode($json, true);
            }

            // определяем страну
            if(isset($data['country_code'])){
                $country = $this->model->getItemByField('geo_countries', 'alpha2', $data['country_code']);
            } else {
                $country = array('id'=>null,'name'=>null);
            }

            cmsUser::sessionSet('cached_geo', $country);

        }

        $this->country = $country;

        return $this;

    }

    private function isInCountry($countries){

        if(empty($this->country['id'])){ return true; }

        if (in_array(0, $countries)){ return true; }

        return in_array($this->country['id'], $countries);

    }

}

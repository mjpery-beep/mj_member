<?php 


class MjTools {
    
    
    
    // Démarrer la session
    public static function start_session() {
        if(! session_id()) {
            session_start();
        }
    }

    // Ajouter des données à la session
    public static function addData($cle, $valeur) {
        self::start_session();
        $user_id = get_current_user_id();
        if ($user_id) {
            return $_SESSION[$cle] = $valeur;
        }
    }

    // Récupérer des données de la session
    public static function getData($cle) {
        self::start_session();
        $user_id = get_current_user_id();
        if ($user_id) {
            return @$_SESSION[$cle];
        }
        return false;
    }

    // Supprimer des données de la session
    public static function removeData($cle) {
        self::start_session();
        $user_id = get_current_user_id();
        if ($user_id) {
            $token = WP_Session_Tokens::get_instance( $user_id );
            $token->destroy($cle);
        }
    }
    
   
    public static function brDisplay($name)
    {
        return str_replace(';', ' <br /> ', $name);
    }
    public static function nameDisplay($name)
    {
        return str_replace(';', ' et ', $name);
    }
    public static function dateDisplay($date)
    {
        setlocale(LC_TIME, 'fr_FR');
        return strftime('%A %e %B %Y', strtotime($date));
    }

    public static function redirect404(){
        // Redirection vers la page 404
        $page_url = home_url('/404'); // Remplacez '/404' par l'URL de votre page 404 personnalisée
        // Redirection vers la page 404 personnalisée
        wp_redirect($page_url);
        exit;
    }

    public static function dump(){
        if(WP_DEBUG)
        {
            echo "<pre>";
            call_user_func_array('var_dump', func_get_args());
            echo "</pre>";
        }
        
    }
    
    /**
     * @return wpdb 
     */
    public static function getWpdb(){
        global $wpdb;
        return $wpdb;
    }
    public static function getTableName($name){
        return self::getWpdb()->prefix . $name; 
    }
}
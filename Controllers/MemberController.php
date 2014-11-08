<?php
/**
 * Created by PhpStorm.
 * User: kaso
 * Date: 11/3/2014
 * Time: 4:08 PM
 */

class MemberController {


    public static $user_id_session = 'current_user_id';

    public static function
    initializeMemberRoutes(\Slim\Slim $app,$authenticateUser,$guestUser){

        $app->group('/user', function () use ($app, $authenticateUser, $guestUser) {

            $app->group('/wallet',  function () use ($app, $authenticateUser) {

                $app->post('/transaction', function () use ($app) {
                });

                $app->get('/balance', function () use ($app) {
                    $app->render('Common/Header.php');
                    $app->render('Wallet/Balance.php');
                    $app->render('Common/Footer.php');
                })->name('wallet_balance');

                $app->get('/fund', function () use ($app) {
                    $app->render('Common/Header.php');
                    $app->render('Wallet/Fund.php', array(
                        'image_url'   => '/Public/images/logo.png',
                        'success_url' => $app->urlFor('fund_success_url'),
                        'failure_url' => $app->urlFor('fund_failure_url')
                    ));
                    $app->render('Common/Footer.php');
                });

                $app->any('/fund/success', function () use ($app) {
                    $user_id = MiddleWare::user_id();
                    static::FundingSuccessHandler($app, $user_id);
                })->name('fund_success_url');

                $app->any('/fund/failure', function () use ($app) {
                    $user_id = MiddleWare::user_id();
                    static::FundingFailureHandler($app, $user_id);
                })->name('fund_failure_url');

                $app->get('/history', function () use ($app) {
                    $app->render('Common/Header.php');
                    $app->render('Wallet/History.php');
                    $app->render('Common/Footer.php');
                });

                $app->get('/apps', function () use ($app) {
                    $app->render('Common/Header.php');
                    $app->render('Wallet/WalletApps.php');
                    $app->render('Common/Footer.php');
                });

                $app->post('/apps/:id', function ($id) use ($app) {

                })->conditions(array('id' => '[a-zA-Z0-9]{10}'));
            });

            $app->get('/logout', function () use ($app) {
                if (static::logoutUser()) {
                    $app->redirect($app->urlFor('login_page'));
                }
            })->name('logout_route');

            $app->get('/login',$guestUser, function () use ($app) {
                $app->render('Common/AuthHeader.php');
                $app->render('User/Login.php');
                $app->render('Common/Footer.php');
            })->name('login_page');

            $app->post('/login', function () use ($app) {
                $email = trim($app->request->post('email', ''));
                $password = trim($app->request->post('password', ''));

                if (static::loginUser($email, $password, $app)) {
                    $app->redirect($app->urlFor('wallet_balance'));
                }
            });

            $app->get('/register',$guestUser, function () use ($app) {
                $app->render('Common/AuthHeader.php');
                $app->render('User/Register.php');
                $app->render('Common/Footer.php');
            })->name('register_page');

            $app->post('/register', function () use ($app) {
                $email = trim($app->request->post('email', ''));
                $password = trim($app->request->post('password', ''));
                $password_conf = trim($app->request->post('password_confirmation', ''));
                if (static::registerUser($email, $password, $password_conf, $app)) {
                    $app->redirect($app->urlFor('wallet_balance'));
                }
            });

            $app->get('/profile',function () use ($app) {
                $app->render('Common/Header.php');
                $app->render('User/Profile-View.php');
                $app->render('Common/Footer.php');
            });

            $app->get('/profile/edit',function () use ($app) {
                $app->render('Common/Header.php');
                $app->render('User/Profile-Edit.php');
                $app->render('Common/Footer.php');
            });

            $app->post('/profile/edit', function () use ($app) {

            });
        });


    }

    public static function loginUser($email,$password,\Slim\Slim $app){

        if(!Utils::isEmail($email) || !Utils::isValidPassword($password)){
            $app->flash('error','Invalid Username or Password Format');
            $app->redirect($app->urlFor('login_page'));
        }

        $user = User::find('first',array('email'=>$email,'password'=>md5($password)));

        if(empty($user)){
            $app->flash('error','Invalid Username or Password');
            $app->redirect($app->urlFor('login_page'));
        }


        $_SESSION[static::$user_id_session] = $user->id;
        return true;
    }


    public static function logoutUser(){
        $_SESSION[static::$user_id_session] = null;
        unset($_SESSION[static::$user_id_session]);
        unset($_SESSION[static::$user_id_session]);
        return true;
    }

    public static function registerUser($email, $password, $password_conf,\Slim\Slim $app)
    {

        if(!Utils::isEmail($email) || !Utils::isValidPassword($password)){
            $app->flash('error','Invalid Username or Password Format');
            $app->redirect($app->urlFor('register_page'));
        }

        if(strcmp($password,$password_conf) != 0){
            $app->flash('error','Password Mismatch');
            $app->redirect($app->urlFor('register_page'));
        }

        $user = User::find('first',array('email'=>$email));
        if(!empty($user)){
            $app->flash('error','User Already Exists');
            $app->redirect($app->urlFor('register_page'));
        }

        $user = User::create([
            'email' => $email,
            'password' => md5($password)
        ]);

        return static::loginUser($email,$password,$app);
    }


    public static function FundingSuccessHandler(\Slim\Slim $app,$user_id){

        $input = $app->request()->params('xmlmsg');

        if(!empty($input)){
            $xml = simplexml_load_string($input);
            $json = json_encode($xml);
            $array = json_decode($json, TRUE);

            $data = [
                'user_id'            => $user_id,
                'transaction_type'   => 'funding',
                'transaction_ref'    => $array['TransactionRef'],
                'transaction_amount' => $array['PurchaseAmount'],
                'transaction_status' => $array['OrderStatus'],
                'amount'  => Utils::getValidPrice($array['PurchaseAmount']),
                'meta_data'          => $input
            ];

            $user = User::find($user_id);
            $user->wallet = $data['amount'];
            $user->save();
            return true;
        }else{
            $app->redirect($app->urlFor('home'));
        }
    }

    public static function FundingFailureHandler(\Slim\Slim $app,$user_id){

        $input = $app->request()->params('xmlmsg');

        if(!empty($input)){
            $xml = simplexml_load_string($input);
            $json = json_encode($xml);
            $array = json_decode($json, TRUE);

            $data = [
                'user_id'            => $user_id,
                'transaction_type'   => 'funding',
                'transaction_ref'    => $array['TransactionRef'],
                'transaction_amount' => $array['PurchaseAmount'],
                'transaction_status' => $array['OrderStatus'],
                'meta_data'          => $input
            ];
        }else{
            $app->redirect($app->urlFor('home'));
        }
    }

}
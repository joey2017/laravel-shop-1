<?php

namespace App\Providers;

use Elasticsearch\ClientBuilder as ESClientBuilder;
use App\Http\ViewComposers\CategoryTreeComposer;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Monolog\Logger;
use Yansongda\Pay\Pay;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        if ($this->app->environment() !== 'production') {
            $this->app->register(\Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider::class);
        }

        // 往服务容器中注入一个名为 alipay 的单例对象
        $this->app->singleton(
            'alipay',
            function () {
                $config = config('pay.alipay');
                // 支付宝异步通知地址
                $config['notify_url'] = ngrok_url('payment.alipay.notify');
                // 支付成功后同步通知地址
                $config['return_url'] = route('payment.alipay.return');
                // 判断当前项目运行环境是否为线上环境
                if (app()->environment() !== 'production') {
                    $config['mode'] = 'dev';
                    $config['log']['level'] = Logger::DEBUG;
                } else {
                    $config['log']['level'] = Logger::WARNING;
                }

                // 调用 Yansongda\Pay 来创建一个支付宝支付对象
                return Pay::alipay($config);
            }
        );
        $this->app->singleton(
            'wechatpay',
            function () {
                $config = config('pay.wechat');
                // 微信支付异步通知地址
                $config['notify_url'] = ngrok_url('payment.wechat.notify');
                // 判断当前项目运行环境是否为线上环境
                if (app()->environment() !== 'production') {
                    $config['log']['level'] = Logger::DEBUG;
                } else {
                    $config['log']['level'] = Logger::WARNING;
                }

                // 调用 Yansongda\Pay 来创建一个微信支付对象
                return Pay::wechat($config);
            }
        );
        // 注册一个名为 es 的单例
        $this->app->singleton(
            'es',
            function () {
                // 从配置文件读取 Elasticsearch 服务器列表
                $builder = ESClientBuilder::create()->setHosts(config('database.elasticsearch.host'));
                // 如果是开发环境
                if (app()->environment() === 'local') {
                    // 配置日志，Elasticsearch 的请求和返回数据将打印到日志文件中，方便调试
                    $builder->setLogger(app('log')->driver());
                }

                return $builder->build();
            }
        );

        // 只有本地开发环境启用 SQL 日志
        if (app()->environment('local')) {
            \DB::listen(
                function ($query) {
                    \Log::info(Str::replaceArray('?', $query->bindings, $query->sql));
                }
            );
        }
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // 当 Laravel 渲染 products.index 和 products.show 模板时，就会使用 CategoryTreeComposer 这个来注入类目树变量
        // 同时 Laravel 还支持通配符，例如 products.* 即代表当渲染 products 目录下的模板时都执行这个 ViewComposer
        \View::composer(['products.index', 'products.show'], CategoryTreeComposer::class);
    }
}

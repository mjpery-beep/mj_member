<?php

namespace Mj\Member\Core;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

if (!defined('ABSPATH')) {
    exit;
}

final class TemplateEngine
{
    private static ?Environment $environment = null;

    /**
     * @param array<string,mixed> $context
     */
    public static function render(string $template, array $context = array()): string
    {
        return self::environment()->render($template, $context);
    }

    /**
     * @param array<string,mixed> $context
     */
    public static function display(string $template, array $context = array()): void
    {
        echo self::render($template, $context); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    private static function environment(): Environment
    {
        if (self::$environment === null) {
            $templatesPath = rtrim(Config::path(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'templates';
            $loader = new FilesystemLoader($templatesPath);

            $options = array(
                'cache' => false,
                'autoescape' => 'html',
            );

            if (defined('WP_DEBUG') && WP_DEBUG) {
                $options['debug'] = true;
            }

            $twig = new Environment($loader, $options);
            self::registerFunctions($twig);
            self::registerFilters($twig);

            self::$environment = $twig;
        }

        return self::$environment;
    }

    private static function registerFunctions(Environment $twig): void
    {
        $twig->addFunction(new TwigFunction('__', static function (string $text, string $domain = 'default'): string {
            return __($text, $domain);
        }));

        $twig->addFunction(new TwigFunction('_x', static function (string $text, string $context, string $domain = 'default'): string {
            return _x($text, $context, $domain);
        }));

        $twig->addFunction(new TwigFunction('_n', static function (string $single, string $plural, int $number, string $domain = 'default'): string {
            return _n($single, $plural, $number, $domain);
        }));

        $twig->addFunction(new TwigFunction('_nx', static function (string $single, string $plural, int $number, string $context, string $domain = 'default'): string {
            return _nx($single, $plural, $number, $context, $domain);
        }));

        $twig->addFunction(new TwigFunction('wp_nonce_field', static function (string $action, string $name = '_wpnonce', bool $referer = true, bool $echo = false): string {
            ob_start();
            wp_nonce_field($action, $name, $referer, $echo);
            return ob_get_clean() ?: '';
        }));

        $twig->addFunction(new TwigFunction('home_url', static function (string $path = '', ?string $scheme = null): string {
            return home_url($path, $scheme ?? '');
        }));

        $twig->addFunction(new TwigFunction('admin_url', static function (string $path = '', ?string $scheme = null): string {
            return admin_url($path, $scheme ?? '');
        }));
    }

    private static function registerFilters(Environment $twig): void
    {
        $twig->addFilter(new TwigFilter('esc_url', static function (?string $value): string {
            return esc_url($value ?? '');
        }));

        $twig->addFilter(new TwigFilter('esc_attr', static function (?string $value): string {
            return esc_attr($value ?? '');
        }));

        $twig->addFilter(new TwigFilter('wp_kses_post', static function (?string $value): string {
            return wp_kses_post($value ?? '');
        }, array('is_safe' => array('html'))));
    }
}

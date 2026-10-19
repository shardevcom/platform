<?php

declare(strict_types=1);

namespace Tests\Unit\Cli;

use PHPUnit\Framework\TestCase;
use Shardevcom\Cli\Workspace\ComponentType;
use Shardevcom\Cli\Workspace\GuardDefinition;
use Shardevcom\Cli\Workspace\GuardPluginScaffolder;
use Tests\Unit\Cli\Support\FakeFilesystem;

/**
 * Adding a guard must give every client plugin a compiling placeholder view and
 * register it, so the new guard renders instead of an empty shell.
 */
final class GuardPluginScaffolderTest extends TestCase
{
    private const ROOT = '/ws';

    public function test_web_adds_a_view_and_registers_it(): void
    {
        $filesystem = $this->filesystem();

        $written = (new GuardPluginScaffolder($filesystem))->scaffold(
            ComponentType::Web,
            self::ROOT.'/web',
            GuardDefinition::create('partner'),
            ['product' => 'products']
        );

        $view = self::ROOT.'/web/plugins/product/src/guards/partner/ProductPartnerView.tsx';
        $this->assertTrue($filesystem->isFile($view));
        $this->assertStringContainsString('export function ProductPartnerView()', $filesystem->read($view));

        $index = $filesystem->read(self::ROOT.'/web/plugins/product/src/index.ts');
        $this->assertStringContainsString('import { ProductPartnerView } from "./guards/partner/ProductPartnerView"', $index);
        $this->assertStringContainsString('partner: {', $index);
        $this->assertStringContainsString('to: "/products"', $index);
        $this->assertContains('plugins/product/src/guards/partner/ProductPartnerView.tsx', $written);
    }

    public function test_mobile_adds_a_view_and_registers_it(): void
    {
        $filesystem = $this->filesystem();

        (new GuardPluginScaffolder($filesystem))->scaffold(
            ComponentType::Mobile,
            self::ROOT.'/mobile',
            GuardDefinition::create('partner'),
            ['product' => 'products']
        );

        $view = self::ROOT.'/mobile/plugins/product/lib/src/guards/partner_view.dart';
        $this->assertTrue($filesystem->isFile($view));
        $this->assertStringContainsString('class ProductPartnerView extends StatelessWidget', $filesystem->read($view));

        $plugin = $filesystem->read(self::ROOT.'/mobile/plugins/product/lib/src/product_plugin.dart');
        $this->assertStringContainsString("import 'guards/partner_view.dart';", $plugin);
        $this->assertStringContainsString('Guard.partner: GuardView(', $plugin);
        $this->assertStringContainsString("path: 'products'", $plugin);
    }

    public function test_desktop_adds_a_view_and_registers_it(): void
    {
        $filesystem = $this->filesystem();

        (new GuardPluginScaffolder($filesystem))->scaffold(
            ComponentType::Desktop,
            self::ROOT.'/desktop',
            GuardDefinition::create('partner'),
            ['product' => 'products']
        );

        $base = self::ROOT.'/desktop/plugins/product/src/main/java/dev/shardev/product';
        $this->assertTrue($filesystem->isFile($base.'/ProductPartnerView.java'));
        $this->assertStringContainsString('package dev.shardev.product;', $filesystem->read($base.'/ProductPartnerView.java'));

        $plugin = $filesystem->read($base.'/ProductPlugin.java');
        $this->assertStringContainsString(
            'Guard.PARTNER, new GuardView("products", context -> new ProductPartnerView(context)',
            $plugin
        );
        $this->assertStringContainsString('Guard.ADMIN', $plugin);
    }

    public function test_it_skips_a_guard_already_registered(): void
    {
        $filesystem = $this->filesystem();

        $scaffolder = new GuardPluginScaffolder($filesystem);
        $scaffolder->scaffold(ComponentType::Web, self::ROOT.'/web', GuardDefinition::create('partner'), ['product' => 'products']);
        $written = $scaffolder->scaffold(ComponentType::Web, self::ROOT.'/web', GuardDefinition::create('partner'), ['product' => 'products']);

        $this->assertSame([], $written);
    }

    public function test_it_skips_a_client_without_the_plugin(): void
    {
        $filesystem = $this->filesystem();

        $written = (new GuardPluginScaffolder($filesystem))->scaffold(
            ComponentType::Web,
            self::ROOT.'/web',
            GuardDefinition::create('partner'),
            ['orders' => 'orders']
        );

        $this->assertSame([], $written);
    }

    private function filesystem(): FakeFilesystem
    {
        return new FakeFilesystem([
            self::ROOT.'/web/plugins/product/src/index.ts' => <<<'TS'
                import { definePlugin } from "@shardev/plugin-kit"

                import { ProductCatalogView } from "./guards/web/ProductCatalogView"

                export const productPlugin = definePlugin({
                  name: "product",
                  title: "Products",
                  resource: "products",
                  guards: {
                    web: {
                      path: "products",
                      component: ProductCatalogView,
                      nav: { label: "Catálogo", to: "/products", order: 10 },
                    },
                  },
                })
                TS,
            self::ROOT.'/mobile/plugins/product/lib/src/product_plugin.dart' => <<<'DART'
                import 'package:flutter/material.dart';
                import 'package:shardev_plugin_kit/shardev_plugin_kit.dart';

                import 'guards/catalog_view.dart';

                final productPlugin = definePlugin(
                  PluginDefinition(
                    name: 'product',
                    title: 'Products',
                    resource: 'products',
                    guards: {
                      Guard.web: GuardView(
                        path: 'products',
                        builder: (_) => const ProductCatalogView(),
                      ),
                    },
                  ),
                );
                DART,
            self::ROOT.'/desktop/plugins/product/src/main/java/dev/shardev/product/ProductPlugin.java' => <<<'JAVA'
                package dev.shardev.product;

                import java.util.Map;

                public final class ProductPlugin {
                    public static PluginDefinition productPlugin() {
                        return new PluginDefinition(
                                "product",
                                "Products",
                                "products",
                                Map.of(
                                        Guard.ADMIN, new GuardView(
                                                "products",
                                                context -> new ProductList(context),
                                                new NavItem("Products", "/products", 10))));
                    }
                }
                JAVA,
        ]);
    }
}

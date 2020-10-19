<?php

declare(strict_types=1);

use Thunder\Shortcode\ShortcodeFacade;

test('test getInstance() method', function () {
    $this->assertInstanceOf(ShortcodeFacade::class, flextype('shortcode')->getInstance());
});

test('test addEventHandler() method', function () {
    $this->assertInstanceOf(ShortcodeFacade::class, flextype('shortcode')->addHandler('foo', static function() { return ''; }));
});

test('test parse() method', function () {
    $this->assertInstanceOf(ShortcodeFacade::class, flextype('shortcode')->addHandler('bar', static function() { return ''; }));
    $this->assertTrue(is_array(flextype('shortcode')->parse('[bar]')));
    $this->assertTrue(is_object(flextype('shortcode')->parse('[bar]')[0]));
});

test('test process() method', function () {
    $this->assertInstanceOf(ShortcodeFacade::class, flextype('shortcode')->addHandler('zed', static function() { return 'Zed'; }));
    $this->assertEquals('Zed', flextype('shortcode')->process('[zed]'));
    $this->assertEquals('fòôBàřZed', flextype('shortcode')->process('fòôBàř[zed]'));
});

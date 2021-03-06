<?php

declare(strict_types=1);

test('test encode() method', function () {
    $this->assertEquals('{"title":"Foo","content":"Bar"}',
                        flextype('json')
                            ->encode(['title' => 'Foo',
                                      'content' => 'Bar']));
});

test('test decode() method', function () {
    $this->assertEquals(['title' => 'Foo',
                         'content' => 'Bar'],
                        flextype('json')
                            ->decode('{"title":"Foo","content":"Bar"}'));
});

test('test getCacheID() method', function () {
    $string = '{"title":"Foo","content":"Bar"}';
    $cache_id = flextype('json')
                    ->getCacheID($string);
    $this->assertEquals(32, strlen($cache_id));
    $this->assertNotEquals($string, $cache_id);
});

<?php

declare(strict_types=1);

beforeEach(function() {
    filesystem()->directory(PATH['project'] . '/uploads')->create();
    filesystem()->directory(PATH['project'] . '/uploads/.meta')->create();
});

afterEach(function (): void {
    filesystem()->directory(PATH['project'] . '/uploads/.meta')->delete();
    filesystem()->directory(PATH['project'] . '/uploads')->delete();
});

test('test update() method', function () {
    flextype('filesystem')->file(PATH['project'] . '/uploads/foo.txt')->put('foo');
    flextype('filesystem')->file(PATH['project'] . '/uploads/.meta/foo.txt.yaml')->put(flextype('yaml')->encode(['title' => 'Foo', 'description' => '', 'type' => 'text/plain', 'filesize' => 3, 'uploaded_on' => 1603090370, 'exif' => []]));

    $this->assertTrue(flextype('media_files_meta')->update('foo.txt', 'description', 'Foo description'));
    $this->assertEquals('Foo description', flextype('yaml')->decode(flextype('filesystem')->file(PATH['project'] . '/uploads/.meta/foo.txt.yaml')->get())['description']);
});

test('test add() method', function () {
    flextype('filesystem')->file(PATH['project'] . '/uploads/foo.txt')->put('foo');
    flextype('filesystem')->file(PATH['project'] . '/uploads/.meta/foo.txt.yaml')->put(flextype('yaml')->encode(['title' => 'Foo', 'description' => '', 'type' => 'text/plain', 'filesize' => 3, 'uploaded_on' => 1603090370, 'exif' => []]));

    $this->assertTrue(flextype('media_files_meta')->add('foo.txt', 'bar', 'Bar'));
    $this->assertEquals('Bar', flextype('yaml')->decode(flextype('filesystem')->file(PATH['project'] . '/uploads/.meta/foo.txt.yaml')->get())['bar']);
});

test('test delete() method', function () {
    flextype('filesystem')->file(PATH['project'] . '/uploads/foo.txt')->put('foo');
    flextype('filesystem')->file(PATH['project'] . '/uploads/.meta/foo.txt.yaml')->put(flextype('yaml')->encode(['title' => 'Foo', 'description' => '', 'type' => 'text/plain', 'filesize' => 3, 'uploaded_on' => 1603090370, 'exif' => []]));

    $this->assertTrue(flextype('media_files_meta')->delete('foo.txt', 'title'));
    $this->assertTrue(empty(flextype('yaml')->decode(flextype('filesystem')->file(PATH['project'] . '/uploads/.meta/foo.txt.yaml')->get())['bar']));
});

test('test getFileMetaLocation() method', function () {
    flextype('filesystem')->file(PATH['project'] . '/uploads/foo.txt')->put('foo');
    flextype('filesystem')->file(PATH['project'] . '/uploads/.meta/foo.txt.yaml')->put(flextype('yaml')->encode(['title' => 'Foo', 'description' => '', 'type' => 'text/plain', 'filesize' => 3, 'uploaded_on' => 1603090370, 'exif' => []]));
    $this->assertStringContainsString('foo.txt.yaml',
                          flextype('media_files_meta')->getFileMetaLocation('foo.txt'));
});

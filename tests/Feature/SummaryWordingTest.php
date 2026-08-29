<?php

namespace LaravelJsonApi\OpenApiSpec\Tests\Feature;

use LaravelJsonApi\OpenApiSpec\Descriptors\Actions\ActionDescriptor;
use LaravelJsonApi\OpenApiSpec\Descriptors\Actions\FetchMany;
use LaravelJsonApi\OpenApiSpec\Tests\TestCase;
use ReflectionClass;

class SummaryWordingTest extends TestCase
{
    private function invokeHelper(string $method, string $argument): string
    {
        $descriptor = (new ReflectionClass(FetchMany::class))->newInstanceWithoutConstructor();

        $target = new \ReflectionMethod(ActionDescriptor::class, $method);
        $target->setAccessible(true);

        return $target->invoke($descriptor, $argument);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function nouns(): array
    {
        return [
            'consonant takes a' => ['game', 'a game'],
            'vowel takes an' => ['achievement', 'an achievement'],
            'e takes an' => ['event', 'an event'],
            'i takes an' => ['image', 'an image'],
            'o takes an' => ['owner', 'an owner'],
            'u that sounds like yoo takes a' => ['user', 'a user'],
            'u that sounds like uh takes an' => ['update', 'an update'],
            'unlock is not a yoo word' => ['unlock', 'an unlock'],
            'uni prefix takes a' => ['unit', 'a unit'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('nouns')]
    public function test_the_article_follows_the_sound_of_the_noun(string $noun, string $expected): void
    {
        $this->assertEquals($expected, $this->invokeHelper('withArticle', $noun));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function identifiers(): array
    {
        return [
            'camelCase relation' => ['playerAchievements', 'player achievements'],
            'kebab-case resource' => ['achievement-set-claims', 'achievement set claims'],
            'single word' => ['games', 'games'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('identifiers')]
    public function test_an_identifier_becomes_words(string $identifier, string $expected): void
    {
        $this->assertEquals($expected, $this->invokeHelper('humanize', $identifier));
    }
}

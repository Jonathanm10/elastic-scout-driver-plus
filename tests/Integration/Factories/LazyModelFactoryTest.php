<?php declare(strict_types=1);

namespace ElasticScoutDriverPlus\Tests\Integration\Factories;

use ElasticAdapter\Search\SearchResponse;
use ElasticScoutDriverPlus\Factories\LazyModelFactory;
use ElasticScoutDriverPlus\Tests\App\Author;
use ElasticScoutDriverPlus\Tests\App\Book;
use ElasticScoutDriverPlus\Tests\Integration\TestCase;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * @covers \ElasticScoutDriverPlus\Factories\LazyModelFactory
 *
 * @uses   \ElasticScoutDriverPlus\Decorators\EngineDecorator
 */
final class LazyModelFactoryTest extends TestCase
{
    public function test_null_value_is_returned_when_trying_to_make_model_from_empty_search_response(): void
    {
        $factory = new LazyModelFactory(collect([new Book()]), new SearchResponse([
            'hits' => [
                'total' => ['value' => 0],
                'hits' => [],
            ],
        ]));

        $this->assertNull($factory->makeById(123));
    }

    public function test_model_can_be_lazy_made_from_not_empty_search_response(): void
    {
        $models = factory(Book::class, rand(2, 10))->create([
            'author_id' => factory(Author::class)->create()->getKey(),
        ]);

        /** @var Connection $connection */
        $connection = DB::connection();
        $connection->enableQueryLog();

        $factory = new LazyModelFactory(collect([new Book()]), new SearchResponse([
            'hits' => [
                'total' => ['value' => $models->count()],
                'hits' => $models->map(static function (Model $model) {
                    return [
                        '_id' => (string)$model->getKey(),
                        '_source' => [],
                    ];
                })->all(),
            ],
        ]));

        // assert that related to search response models are returned
        $models->each(function (Model $expected) use ($factory) {
            $actual = $factory->makeById($expected->getScoutKey());

            $this->assertNotNull($actual);
            /** @var Model $actual */
            $this->assertEquals($expected->toArray(), $actual->toArray());
        });

        // assert that the only one query to the database is made
        $this->assertCount(1, $connection->getQueryLog());
    }
}

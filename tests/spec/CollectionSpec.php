<?php

namespace spec\DusanKasan\Knapsack;

use ArrayIterator;
use DOMXPath;
use DusanKasan\Knapsack\Collection;
use DusanKasan\Knapsack\Exceptions\InvalidArgument;
use DusanKasan\Knapsack\Exceptions\InvalidReturnValue;
use DusanKasan\Knapsack\Exceptions\ItemNotFound;
use DusanKasan\Knapsack\Exceptions\NoMoreItems;
use DusanKasan\Knapsack\Tests\Helpers\PlusOneAdder;
use Iterator;
use IteratorAggregate;
use PhpSpec\ObjectBehavior;
use Serializable;
use function DusanKasan\Knapsack\reverse;

/**
 * @mixin Collection
 */
class CollectionSpec extends ObjectBehavior
{
    function it_is_initializable()
    {
        $this->beConstructedWith([1, 2, 3]);
        $this->shouldHaveType(Collection::class);
        $this->shouldHaveType(Iterator::class);
    }

    function it_can_be_instantiated_from_iterator()
    {
        $iterator = new ArrayIterator([1, 2]);
        $this->beConstructedWith($iterator);
        $this->toArray()->shouldReturn([1, 2]);
    }

    function it_can_be_instantiated_from_iterator_aggregate(IteratorAggregate $iteratorAggregate)
    {
        $iterator = new ArrayIterator([1, 2]);
        $iteratorAggregate->getIterator()->willReturn($iterator);
        $this->beConstructedWith($iteratorAggregate);
        $this->toArray()->shouldReturn([1, 2]);
    }

    function it_can_be_instantiated_from_array()
    {
        $this->beConstructedWith([1, 2, 3]);
        $this->toArray()->shouldReturn([1, 2, 3]);
    }

    function it_will_throw_when_passed_something_other_than_array_or_traversable()
    {
        $this->beConstructedWith(1);
        $this->shouldThrow(InvalidArgument::class)->duringInstantiation();
    }

    function it_can_be_created_statically()
    {
        $this->beConstructedThrough('from', [[1, 2]]);
        $this->toArray()->shouldReturn([1, 2]);
    }

    function it_can_be_created_to_iterate_over_function_infinitely()
    {
        $this->beConstructedThrough('iterate', [1, function($i) {return $i+1;}]);
        $this->take(2)->toArray()->shouldReturn([1, 2]);
    }

    function it_can_be_created_to_iterate_over_function_non_infinitely()
    {
        $this->beConstructedThrough(
            'iterate',
            [
                1,
                function($i) {
                    if ($i > 3) {
                        throw new NoMoreItems;
                    }

                    return $i+1;
                }
            ]
        );
        $this->toArray()->shouldReturn([1, 2, 3, 4]);
    }

    function it_can_be_created_to_repeat_a_value_infinite_times()
    {
        $this->beConstructedThrough('repeat', [1]);
        $this->take(2)->toArray()->shouldReturn([1, 1]);
    }

    function it_can_filter()
    {
        $this->beConstructedWith([1, 3, 3, 2,]);

        $this
            ->filter(function ($item) {
                return $item > 2;
            })
            ->toArray()
            ->shouldReturn([1 => 3, 2 => 3]);

        $this
            ->filter(function ($item, $key) {
                return $key > 2 && $item < 3;
            })
            ->toArray()
            ->shouldReturn([3 => 2]);
    }

    function it_can_distinct()
    {
        $this->beConstructedWith([1, 3, 3, 2,]);

        $this
            ->distinct()
            ->toArray()
            ->shouldReturn([1, 3, 3 => 2]);
    }

    function it_can_concat()
    {
        $this->beConstructedWith([1, 3, 3, 2,]);

        $collection = $this->concat([4, 5]);
        $collection->toArray()->shouldReturn([4, 5, 3, 2]);
        $collection->size()->shouldReturn(6);
    }

    function it_can_map()
    {
        $this->beConstructedWith([1, 3, 3, 2,]);

        $this
            ->map(function ($item) {
                return $item + 1;
            })
            ->toArray()
            ->shouldReturn([2, 4, 4, 3]);
    }

    function it_can_reduce()
    {
        $this->beConstructedWith([1, 3, 3, 2,]);

        $this
            ->reduce(
                function ($temp, $item) {
                    $temp[] = $item;

                    return $temp;
                },
                ['a' => [1]],
                true
            )
            ->values()
            ->toArray()
            ->shouldReturn([[1], 1, 3, 3, 2]);

        $this
            ->reduce(
                function ($temp, $item) {
                    return $temp + $item;
                },
                0
            )
            ->shouldReturn(9);

        $this
            ->reduce(
                function ($temp, $item, $key) {
                    return $temp + $key + $item;
                },
                0
            )
            ->shouldReturn(15);

        $this
            ->reduce(
                function (Collection $temp, $item) {
                    return $temp->append($item);
                },
                new Collection([])
            )
            ->toArray()
            ->shouldReturn([1, 3, 3, 2]);
    }

    function it_can_flatten()
    {
        $this->beConstructedWith([1, [2, [3]]]);
        $this->flatten()->values()->toArray()->shouldReturn([1, 2, 3]);
        $this->flatten(1)->values()->toArray()->shouldReturn([1, 2, [3]]);
    }

    function it_can_sort()
    {
        $this->beConstructedWith([3, 1, 2]);

        $this
            ->sort(function ($a, $b) {
                return $a > $b;
            })
            ->toArray()
            ->shouldReturn([1 => 1, 2 => 2, 0 => 3]);

        $this
            ->sort(function ($v1, $v2, $k1, $k2) {
                return $k1 < $k2 || $v1 == $v2;
            })
            ->toArray()
            ->shouldReturn([2 => 2, 1 => 1, 0 => 3]);
    }

    function it_can_slice()
    {
        $this->beConstructedWith([1, 2, 3, 4, 5]);

        $this
            ->slice(2, 4)
            ->toArray()
            ->shouldReturn([2 => 3, 3 => 4]);

        $this
            ->slice(4)
            ->toArray()
            ->shouldReturn([4 => 5]);
    }

    function it_can_group_by()
    {
        $this->beConstructedWith([1, 2, 3, 4, 5]);

        $collection = $this->groupBy(function ($i) {
            return $i % 2;
        });

        $collection->get(0)->toArray()->shouldReturn([2, 4]);
        $collection->get(1)->toArray()->shouldReturn([1, 3, 5]);

        $collection = $this->groupBy(function ($k, $i) {
            return ($k + $i) % 3;
        });
        $collection->get(0)->toArray()->shouldReturn([2, 5]);
        $collection->get(1)->toArray()->shouldReturn([1, 4]);
        $collection->get(2)->toArray()->shouldReturn([3]);
    }

    function it_can_group_by_key()
    {
        $this->beConstructedWith([
            'some' => 'thing',
            ['letter' => 'A', 'type' => 'caps'],
            ['letter' => 'a', 'type' => 'small'],
            ['letter' => 'B', 'type' => 'caps'],
            ['letter' => 'Z']
        ]);

        $collection = $this->groupByKey('type');
        $collection->get('small')->toArray()->shouldReturn([
            ['letter' => 'a', 'type' => 'small']
        ]);
        $collection->get('caps')->toArray()->shouldReturn([
            ['letter' => 'A', 'type' => 'caps'],
            ['letter' => 'B', 'type' => 'caps']
        ]);

        $collection = $this->groupByKey('types');
        $collection->shouldThrow(new ItemNotFound)->during('get', ['caps']);
    }

    function it_can_execute_callback_for_each_item(DOMXPath $a)
    {
        $a->query('asd')->shouldBeCalled();
        $this->beConstructedWith([$a]);

        $this
            ->each(function (DOMXPath $i) {
                $i->query('asd');
            })
            ->toArray()
            ->shouldReturn([$a]);
    }

    function it_can_get_size()
    {
        $this->beConstructedWith([1, 3, 3, 2,]);
        $this->size()->shouldReturn(4);
    }

    function it_can_get_item_by_key()
    {
        $this->beConstructedWith([1, [2], 3]);
        $this->get(0)->shouldReturn(1);
        $this->get(1, true)->first()->shouldReturn(2);
        $this->get(1)->shouldReturn([2]);
        $this->shouldThrow(new ItemNotFound)->during('get', [5]);
    }

    function it_can_get_item_by_key_or_return_default()
    {
        $this->beConstructedWith([1, [2], 3]);
        $this->getOrDefault(0)->shouldReturn(1);
        $this->getOrDefault(1, null, true)->first()->shouldReturn(2);
        $this->getOrDefault(1, null)->shouldReturn([2]);
        $this->getOrDefault(5)->shouldReturn(null);
        $this->getOrDefault(5, 'not found')->shouldReturn('not found');
    }

    function it_can_find()
    {
        $this->beConstructedWith([1, 3, 3, 2, [5]]);

        $this
            ->find(function ($v) {
                return $v < 3;
            })
            ->shouldReturn(1);

        $this
            ->find(function ($v, $k) {
                return $v < 3 && $k > 1;
            })
            ->shouldReturn(2);

        $this
            ->find(function ($v) {
                return $v < 0;
            })
            ->shouldReturn(null);

        $this
            ->find(
                function ($v) {
                    return $v < 0;
                },
                'not found'
            )
            ->shouldReturn('not found');

        $this->find('\DusanKasan\Knapsack\isCollection', null, true)->first()->shouldReturn(5);
        $this->find('\DusanKasan\Knapsack\isCollection')->shouldReturn([5]);
    }

    function it_can_count_by()
    {
        $this->beConstructedWith([1, 3, 3, 2,]);

        $this
            ->countBy(function ($v) {
                return $v % 2 == 0 ? 'even' : 'odd';
            })
            ->toArray()
            ->shouldReturn(['odd' => 3, 'even' => 1]);

        $this
            ->countBy(function ($k, $v) {
                return ($k + $v) % 2 == 0 ? 'even' : 'odd';
            })
            ->toArray()
            ->shouldReturn(['odd' => 3, 'even' => 1]);
    }

    function it_can_index_by()
    {
        $this->beConstructedWith([1, 3, 3, 2,]);

        $this
            ->indexBy(function ($v) {
                return $v;
            })
            ->toArray()
            ->shouldReturn([1 => 1, 3 => 3, 2 => 2]);

        $this
            ->indexBy(function ($v, $k) {
                return $k . $v;
            })
            ->toArray()
            ->shouldReturn(['01' => 1, '13' => 3, '23' => 3, '32' => 2]);
    }

    function it_can_check_if_every_item_passes_predicament_test()
    {
        $this->beConstructedWith([1, 3, 3, 2,]);

        $this
            ->every(function ($v) {
                return $v > 0;
            })
            ->shouldReturn(true);

        $this
            ->every(function ($v) {
                return $v > 1;
            })
            ->shouldReturn(false);

        $this
            ->every(function ($v, $k) {
                return $v > 0 && $k >= 0;
            })
            ->shouldReturn(true);

        $this
            ->every(function ($v, $k) {
                return $v > 0 && $k > 0;
            })
            ->shouldReturn(false);
    }

    function it_can_check_if_some_items_pass_predicament_test()
    {
        $this->beConstructedWith([1, 3, 3, 2,]);

        $this
            ->some(function ($v) {
                return $v < -1;
            })
            ->shouldReturn(false);

        $this
            ->some(function ($v, $k) {
                return $v > 0 && $k < -1;
            })
            ->shouldReturn(false);

        $this
            ->some(function ($v) {
                return $v < 2;
            })
            ->shouldReturn(true);

        $this
            ->some(function ($v, $k) {
                return $v > 0 && $k > 0;
            })
            ->shouldReturn(true);
    }

    function it_can_check_if_it_contains_a_value()
    {
        $this->beConstructedWith([1, 3, 3, 2,]);

        $this
            ->contains(3)
            ->shouldReturn(true);

        $this
            ->contains(true)
            ->shouldReturn(false);
    }

    function it_can_reverse()
    {
        $this->beConstructedWith([1, 3, 3, 2,]);

        $collection = $this->reverse();

        $collection->rewind();
        $collection->valid()->shouldReturn(true);
        $collection->key()->shouldReturn(3);
        $collection->current()->shouldReturn(2);
        $collection->next();
        $collection->valid()->shouldReturn(true);
        $collection->key()->shouldReturn(2);
        $collection->current()->shouldReturn(3);
        $collection->next();
        $collection->valid()->shouldReturn(true);
        $collection->key()->shouldReturn(1);
        $collection->current()->shouldReturn(3);
        $collection->next();
        $collection->valid()->shouldReturn(true);
        $collection->key()->shouldReturn(0);
        $collection->current()->shouldReturn(1);
        $collection->next();
        $collection->valid()->shouldReturn(false);
    }

    function it_can_reduce_from_right()
    {
        $this->beConstructedWith([1, 3, 3, 2,]);

        $this
            ->reduceRight(
                function ($temp, $e) {
                    return $temp . $e;
                },
                0
            )
            ->shouldReturn('02331');

        $this
            ->reduceRight(
                function ($temp, $key, $item) {
                    return $temp + $key + $item;
                },
            0
            )
            ->shouldReturn(15);

        $this
            ->reduceRight(
                function (Collection $temp, $item) {
                    return $temp->append($item);
                },
                new Collection([])
            )
            ->toArray()
            ->shouldReturn([2, 3, 3, 1]);
    }

    function it_can_return_only_first_x_elements()
    {
        $this->beConstructedWith([1, 3, 3, 2,]);

        $this->take(2)
            ->toArray()
            ->shouldReturn([1, 3]);
    }

    function it_can_skip_first_x_elements()
    {
        $this->beConstructedWith([1, 3, 3, 2,]);

        $this->drop(2)
            ->toArray()
            ->shouldReturn([2 => 3, 3 => 2]);
    }

    function it_can_return_values()
    {
        $this->beConstructedWith(['a' => 1, 'b' => 2]);
        $this->values()->toArray()->shouldReturn([1, 2]);
    }


    function it_can_reject_elements_from_collection()
    {
        $this->beConstructedWith([1, 3, 3, 2,]);

        $this
            ->reject(function ($v) {
                return $v == 3;
            })
            ->toArray()
            ->shouldReturn([1, 3 => 2]);

        $this
            ->reject(function ($v, $k) {
                return $k == 2 && $v == 3;
            })
            ->toArray()
            ->shouldReturn([1, 3, 3 => 2]);
    }

    function it_can_get_keys()
    {
        $this->beConstructedWith([1, 3, 3, 2,]);

        $this
            ->keys()
            ->toArray()
            ->shouldReturn([0, 1, 2, 3]);
    }

    function it_can_interpose()
    {
        $this->beConstructedWith([1, 3, 3, 2,]);

        $this
            ->interpose('a')
            ->values()
            ->toArray()
            ->shouldReturn([1, 'a', 3, 'a', 3, 'a', 2]);
    }

    function it_can_drop_elements_from_end_of_the_collection()
    {
        $this->beConstructedWith([1, 3, 3, 2,]);

        $this
            ->dropLast()
            ->toArray()
            ->shouldReturn([1, 3, 3]);

        $this
            ->dropLast(2)
            ->toArray()
            ->shouldReturn([1, 3]);
    }

    function it_can_interleave_elements()
    {
        $this->beConstructedWith([1, 3, 3, 2,]);

        $this
            ->interleave(['a', 'b', 'c', 'd', 'e'])
            ->values()
            ->toArray()
            ->shouldReturn([1, 'a', 3, 'b', 3, 'c', 2, 'd', 'e']);
    }

    function it_can_repeat_items_of_collection_infinitely()
    {
        $this->beConstructedWith([1, 3, 3, 2,]);

        $this
            ->cycle()
            ->take(8)
            ->values()
            ->toArray()
            ->shouldReturn([1, 3, 3, 2, 1, 3, 3, 2]);
    }

    function it_can_prepend_item()
    {
        $this->beConstructedWith([1, 3, 3, 2,]);

        $this
            ->prepend(1)
            ->values()
            ->toArray()
            ->shouldReturn([1, 1, 3, 3, 2]);
    }

    function it_can_prepend_item_with_key()
    {
        $this->beConstructedWith([1, 3, 3, 2,]);

        $this
            ->prepend(1, 'a')
            ->toArray()
            ->shouldReturn(['a' => 1, 0 => 1, 1 => 3, 2 => 3, 3 => 2]);
    }

    function it_can_append_item()
    {
        $this->beConstructedWith([1, 3, 3, 2,]);

        $this
            ->append(1)
            ->values()
            ->toArray()
            ->shouldReturn([1, 3, 3, 2, 1]);
    }

    function it_can_append_item_with_key()
    {
        $this->beConstructedWith([1, 3, 3, 2,]);

        $this
            ->append(1, 'a')
            ->toArray()
            ->shouldReturn([1, 3, 3, 2, 'a' => 1]);
    }

    function it_can_drop_items_while_predicament_is_true()
    {
        $this->beConstructedWith([1, 3, 3, 2,]);

        $this
            ->dropWhile(function ($v) {
                return $v < 3;
            })
            ->toArray()
            ->shouldReturn([1 => 3, 2 => 3, 3 => 2]);

        $this
            ->dropWhile(function ($v, $k) {
                return $k < 2 && $v < 3;
            })
            ->toArray()
            ->shouldReturn([1 => 3, 2 => 3, 3 => 2]);
    }

    function it_can_map_and_then_concatenate_a_collection()
    {
        $this->beConstructedWith([1, 3, 3, 2,]);

        $this
            ->mapcat(function ($v) {
                return [[$v]];
            })
            ->values()
            ->toArray()
            ->shouldReturn([[1], [3], [3], [2]]);

        $this
            ->mapcat(function ($v, $k) {
                return [[$k + $v]];
            })
            ->values()
            ->toArray()
            ->shouldReturn([[1], [4], [5], [5]]);
    }

    function it_can_take_items_while_predicament_is_true()
    {
        $this->beConstructedWith([1, 3, 3, 2,]);

        $this
            ->takeWhile(function ($v) {
                return $v < 3;
            })
            ->toArray()
            ->shouldReturn([1]);

        $this
            ->takeWhile(function ($v, $k) {
                return $k < 2 && $v < 3;
            })
            ->toArray()
            ->shouldReturn([1]);
    }

    function it_can_split_the_collection_at_nth_item()
    {
        $this->beConstructedWith([1, 3, 3, 2,]);

        $this->splitAt(2)->size()->shouldBe(2);
        $this->splitAt(2)->first()->toArray()->shouldBeLike([1, 3]);
        $this->splitAt(2)->second()->toArray()->shouldBeLike([2 => 3, 3 => 2]);
    }

    function it_can_split_the_collection_with_predicament()
    {
        $this->beConstructedWith([1, 3, 3, 2,]);

        $s1 = $this->splitWith(function ($v) {
            return $v < 3;
        });

        $s1->size()->shouldBe(2);
        $s1->first()->toArray()->shouldBe([1]);
        $s1->second()->toArray()->shouldBe([1 => 3, 2 => 3, 3 => 2]);

        $s2 = $this->splitWith(function ($v, $k) {
            return $v < 2 && $k < 3;
        });

        $s2->size()->shouldBe(2);
        $s2->first()->toArray()->shouldBe([1]);
        $s2->second()->toArray()->shouldBe([1 => 3, 2 => 3, 3 => 2]);
    }

    function it_can_replace_items_by_items_from_another_collection()
    {
        $this->beConstructedWith([1, 3, 3, 2,]);

        $this
            ->replace([3 => 'a'])
            ->toArray()
            ->shouldReturn([1, 'a', 'a', 2]);
    }

    function it_can_get_reduction_steps()
    {
        $this->beConstructedWith([1, 3, 3, 2,]);

        $this
            ->reductions(
                function ($tmp, $i) {
                    return $tmp + $i;
                },
                0
            )
            ->toArray()
            ->shouldReturn([0, 1, 4, 7, 9]);
    }

    function it_can_return_every_nth_item()
    {
        $this->beConstructedWith([1, 3, 3, 2,]);

        $this->takeNth(2)
            ->toArray()
            ->shouldReturn([1, 2 => 3]);
    }

    function it_can_shuffle_itself()
    {
        $this->beConstructedWith([1, 3, 3, 2,]);

        $this
            ->shuffle()
            ->reduce(
                function ($tmp, $i) {
                    return $tmp + $i;
                },
                0
            )
            ->shouldReturn(9);
    }

    function it_can_partition()
    {
        $this->beConstructedWith([1, 3, 3, 2,]);

        $s1 = $this->partition(3, 2, [0, 1]);
        $s1->size()->shouldBe(2);
        $s1->first()->toArray()->shouldBe([1, 3, 3]);
        $s1->second()->toArray()->shouldBe([2 => 3, 3 => 2, 0 => 0]);

        $s2 = $this->partition(3, 2);
        $s2->size()->shouldBe(2);
        $s2->first()->toArray()->shouldBe([1, 3, 3]);
        $s2->second()->toArray()->shouldBe([2 => 3, 3 => 2]);

        $s3 = $this->partition(3);
        $s3->size()->shouldBe(2);
        $s3->first()->toArray()->shouldBe([1, 3, 3]);
        $s3->second()->toArray()->shouldBe([3 => 2]);

        $s4 = $this->partition(1, 3);
        $s4->size()->shouldBe(2);
        $s4->first()->toArray()->shouldBe([1,]);
        $s4->second()->toArray()->shouldBe([3 => 2]);
    }

    function it_can_partition_by()
    {
        $this->beConstructedWith([1, 3, 3, 2]);

        $s1 = $this->partitionBy(function ($v) {
            return $v % 3 == 0;
        });
        $s1->size()->shouldBe(3);
        $s1->first()->toArray()->shouldBe([1]);
        $s1->second()->toArray()->shouldBe([1 => 3, 2 => 3]);
        $s1->values()->get(2)->toArray()->shouldBe([3 => 2]);


        $s2 = $this->partitionBy(function ($v, $k) {
            return $k - $v;
        });
        $s2->size()->shouldBe(4);
        $s2->first()->toArray()->shouldBe([1]);
        $s2->values()->get(1)->toArray()->shouldBe([1 => 3]);
        $s2->values()->get(2)->toArray()->shouldBe([2 => 3]);
        $s2->values()->get(3)->toArray()->shouldBe([3 => 2]);
    }

    function it_can_get_nth_value()
    {
        $this->beConstructedWith([1, 3, 3, 2,]);

        $this->first(0)->shouldReturn(1);
        $this->values()->get(3)->shouldReturn(2);
    }

    function it_can_create_infinite_collection_of_repeated_values()
    {
        $this->beConstructedThrough('repeat', [1]);
        $this->take(3)->toArray()->shouldReturn([1, 1, 1]);
    }

    function it_can_create_finite_collection_of_repeated_values()
    {
        $this->beConstructedThrough('repeat', [1, 1]);
        $this->toArray()->shouldReturn([1]);
    }

    function it_can_create_range_from_value_to_infinity()
    {
        $this->beConstructedThrough('range', [5]);
        $this->take(2)->toArray()->shouldReturn([5, 6]);
    }

    function it_can_create_range_from_value_to_another_value()
    {
        $this->beConstructedThrough('range', [5, 6]);
        $this->take(4)->toArray()->shouldReturn([5, 6]);
    }

    function it_can_check_if_it_is_not_empty()
    {
        $this->beConstructedWith([1, 3, 3, 2,]);

        $this->isEmpty()->shouldReturn(false);
        $this->isNotEmpty()->shouldReturn(true);
    }

    function it_can_check_if_it_is_empty()
    {
        $this->beConstructedWith([]);

        $this->isEmpty()->shouldReturn(true);
        $this->isNotEmpty()->shouldReturn(false);
    }

    function it_can_check_frequency_of_distinct_items()
    {
        $this->beConstructedWith([1, 3, 3, 2,]);

        $this
            ->frequencies()
            ->toArray()
            ->shouldReturn([1 => 1, 3 => 2, 2 => 1]);
    }

    function it_can_get_first_item()
    {
        $this->beConstructedWith([1, [2], 3]);
        $this->first()->shouldReturn(1);
        $this->drop(1)->first()->shouldReturn([2]);
        $this->drop(1)->first(true)->toArray()->shouldReturn([2]);
    }

    function it_will_throw_when_trying_to_get_first_item_of_empty_collection()
    {
        $this->beConstructedWith([]);
        $this->shouldThrow(ItemNotFound::class)->during('first');
    }

    function it_can_get_last_item()
    {
        $this->beConstructedWith([1, [2], 3]);
        $this->last()->shouldReturn(3);
        $this->take(2)->last()->shouldReturn([2]);
        $this->take(2)->last(true)->toArray()->shouldReturn([2]);
    }

    function it_will_throw_when_trying_to_get_last_item_of_empty_collection()
    {
        $this->beConstructedWith([]);
        $this->shouldThrow(ItemNotFound::class)->during('last');
    }

    function it_can_realize_the_collection(PlusOneAdder $adder)
    {
        $adder->dynamicMethod(1)->willReturn(2);
        $adder->dynamicMethod(2)->willReturn(3);

        $this->beConstructedWith(function () use ($adder) {
            yield $adder->dynamicMethod(1);
            yield $adder->dynamicMethod(2);
        });

        $this->realize();
    }

    function it_can_combine_the_collection()
    {
        $this->beConstructedWith(['a', 'b']);
        $this->combine([1, 2])->toArray()->shouldReturn(['a' => 1, 'b' => 2]);
        $this->combine([1])->toArray()->shouldReturn(['a' => 1]);
        $this->combine([1, 2, 3])->toArray()->shouldReturn(['a' => 1, 'b' => 2]);
    }

    function it_can_get_second_item()
    {
        $this->beConstructedWith([1, 2]);
        $this->second()->shouldReturn(2);
    }

    function it_throws_when_trying_to_get_non_existent_second_item()
    {
        $this->beConstructedWith([1]);
        $this->shouldThrow(ItemNotFound::class)->during('second');
    }

    function it_can_drop_item_by_key()
    {
        $this->beConstructedWith(['a' => 1, 'b' => 2]);
        $this->except(['a', 'b'])->toArray()->shouldReturn([]);
    }

    function it_can_get_the_difference_between_collections()
    {
        $this->beConstructedWith([1, 2, 3, 4]);
        $this->diff([1, 2])->toArray()->shouldReturn([2 => 3, 3 => 4]);
        $this->diff([1, 2], [3])->toArray()->shouldReturn([3 => 4]);
    }

    function it_can_flip_the_collection()
    {
        $this->beConstructedWith(['a' => 1, 'b' => 2]);
        $this->flip()->toArray()->shouldReturn([1 => 'a', 2 => 'b']);
    }

    function it_can_check_if_key_exits()
    {
        $this->beConstructedWith(['a' => 1, 'b' => 2]);
        $this->has('a')->shouldReturn(true);
        $this->has('x')->shouldReturn(false);
    }

    function it_filters_by_keys()
    {
        $this->beConstructedWith(['a' => 1, 'b' => 2, 'c' => 3]);
        $this->only(['a', 'b'])->toArray()->shouldReturn(['a' => 1, 'b' => 2]);
        $this->only(['a', 'b', 'x'])->toArray()->shouldReturn(['a' => 1, 'b' => 2]);
    }

    function it_can_serialize_and_unserialize()
    {
        $original = Collection::from([1, 2, 3])->take(2);
        $this->beConstructedWith([1, 2, 3, 4]);
        $this->shouldHaveType(Serializable::class);
        $this->unserialize($original->serialize());
        $this->toArray()->shouldReturn([1, 2]);
    }

    function it_can_zip()
    {
        $this->beConstructedWith([1, 2, 3]);
        $this->zip(['a' => 1, 'b' => 2, 'c' => 4])
            ->map('\DusanKasan\Knapsack\toArray')
            ->toArray()
            ->shouldReturn([[1, 'a' => 1], [1 => 2, 'b' => 2], [2 => 3, 'c' => 4]]);

        $this->zip([4, 5, 6], [7, 8, 9])
            ->map('\DusanKasan\Knapsack\values')
            ->map('\DusanKasan\Knapsack\toArray')
            ->toArray()
            ->shouldReturn([[1, 4, 7], [2, 5, 8], [3, 6, 9]]);

        $this->zip([4, 5])
            ->map('\DusanKasan\Knapsack\values')
            ->map('\DusanKasan\Knapsack\toArray')
            ->toArray()
            ->shouldReturn([[1, 4], [2, 5]]);
    }

    function it_can_use_callable_as_transformer()
    {
        $this->beConstructedWith([1, 2, 3]);
        $this
            ->transform(function (Collection $collection) {
                return $collection->map('\DusanKasan\Knapsack\increment');
            })
            ->toArray()
            ->shouldReturn([2, 3, 4]);

        $this
            ->shouldThrow(InvalidReturnValue::class)
            ->during(
                'transform',
                [
                    function (Collection $collection) {
                        return $collection->first();
                    }
                ]
            );
    }

    function it_can_extract_data_from_nested_collections()
    {
        $input = [
            [
                'a' => [
                    'b' => 1
                ]
            ],
            [
                'a' => [
                    'b' => 2
                ]
            ],
            [
                '*' => [
                    'b' => 3
                ]
            ],
            [
                '.' => [
                    'b' => 4
                ],
                'c' => [
                    'b' => 5
                ]
            ]
        ];
        $this->beConstructedWith($input);

        $this->extract('')->toArray()->shouldReturn($input);
        $this->extract('a.b')->toArray()->shouldReturn([1, 2]);
        $this->extract('*.b')->toArray()->shouldReturn([1, 2, 3, 4, 5]);
        $this->extract('\*.b')->toArray()->shouldReturn([3]);
        $this->extract('\..b')->toArray()->shouldReturn([4]);
    }

    function it_can_get_the_intersect_of_collections()
    {
        $this->beConstructedWith([1, 2, 3]);
        $this->intersect([1, 2])->values()->toArray()->shouldReturn([1, 2]);
        $this->intersect([1], [3])->values()->toArray()->shouldReturn([1, 3]);
    }

    function it_can_use_the_utility_methods()
    {
        $this->beConstructedWith([1, 3, 2]);

        $this
            ->sort('\DusanKasan\Knapsack\compare')
            ->values()
            ->toArray()
            ->shouldReturn([1, 2, 3]);
        
        $this
            ->map('\DusanKasan\Knapsack\compare')
            ->toArray()
            ->shouldReturn([1, 1, 0]);

        $this
            ->map('\DusanKasan\Knapsack\decrement')
            ->toArray()
            ->shouldReturn([0, 2, 1]);

        $this
            ->reduce('\DusanKasan\Knapsack\sum', 0)
            ->shouldReturn(9);

        $this
            ->reduce('\DusanKasan\Knapsack\max', 0)
            ->shouldReturn(3);

        $this
            ->reduce('\DusanKasan\Knapsack\min', 1)
            ->shouldReturn(0);

        $this
            ->map('\DusanKasan\Knapsack\average')
            ->toArray()
            ->shouldReturn([0.5, 2, 2]);

        $this
            ->reduce('\DusanKasan\Knapsack\concatenate', '')
            ->shouldReturn('103122');
    }
}


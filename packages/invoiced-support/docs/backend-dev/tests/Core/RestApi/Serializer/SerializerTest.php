<?php

namespace App\Tests\Core\RestApi\Serializer;

use App\Core\RestApi\Interfaces\EncoderInterface;
use App\Core\RestApi\Interfaces\NormalizerInterface;
use App\Core\RestApi\Serializer\Serializer;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use Symfony\Component\HttpFoundation\Response;

class SerializerTest extends MockeryTestCase
{
    public function testSerialize(): void
    {
        $normalizer1 = Mockery::mock(NormalizerInterface::class);
        $normalizer1->shouldReceive('normalize')
            ->andReturn(null);

        $normalizer2 = Mockery::mock(NormalizerInterface::class);
        $normalizer2->shouldReceive('normalize')
            ->andReturn(['test' => true]);

        $encoder = Mockery::mock(EncoderInterface::class);
        $encoder->shouldReceive('encode')
            ->andReturnUsing(function ($input, $response) {
                $response->setContent(json_encode($input));

                return $response;
            });

        $serializer = new Serializer($encoder);
        $serializer->addNormalizer($normalizer1)
            ->addNormalizer($normalizer2);

        $response = new Response();

        $response = $serializer->serialize(1, $response);
        $this->assertEquals('{"test":true}', $response->getContent());

        $this->assertEquals([$normalizer1, $normalizer2], $serializer->getNormalizers());
    }
}

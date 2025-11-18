<?php

namespace App\Tests\Controller;

use App\Entity\Producto;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class ProductoControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private ?EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $kernel = self::bootKernel();

        // Get the entity manager
        $this->entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();

        // Ensure the database is clean before each test
        $this->runCommand('doctrine:database:drop', ['--force' => true, '--if-exists' => true]);
        $this->runCommand('doctrine:database:create');
        $this->runCommand('doctrine:migrations:migrate', ['-n' => true]);
    }

    public function testCreateProducto(): void
    {
        $this->client->request(
            'POST',
            '/api/producto',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'nombre' => 'Test Producto',
                'serial' => '12345',
                'marca' => 'Test Brand',
                'modelo' => 'Test Model',
                'sede' => 'Sede Central',
                'oficina' => 'Oficina 101',
                'detalle' => 'Detalles del producto de test.',
                'ubicacion' => 'Estante A',
            ])
        );

        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(201);
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('Test Producto', $response['nombre']);

        // Verify the product was created in the database
        $producto = $this->entityManager->getRepository(Producto::class)->findOneBy(['nombre' => 'Test Producto']);
        $this->assertNotNull($producto);
    }

    public function testShowProducto(): void
    {
        $producto = $this->createProducto();

        $this->client->request('GET', '/api/producto/' . $producto->getId());

        $this->assertResponseIsSuccessful();
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals($producto->getNombre(), $response['nombre']);
    }

    public function testListProductos(): void
    {
        $this->createProducto('Producto 1');
        $this->createProducto('Producto 2');

        $this->client->request('GET', '/api/producto');

        $this->assertResponseIsSuccessful();
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(2, $response);
    }

    public function testUpdateProducto(): void
    {
        $producto = $this->createProducto();

        $this->client->request(
            'PUT',
            '/api/producto/' . $producto->getId(),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['nombre' => 'Updated Producto'])
        );

        $this->assertResponseIsSuccessful();
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('Updated Producto', $response['nombre']);

        // Verify the update in the database
        $this->entityManager->clear();
        $updatedProducto = $this->entityManager->getRepository(Producto::class)->find($producto->getId());
        $this->assertEquals('Updated Producto', $updatedProducto->getNombre());
    }

    public function testDeleteProducto(): void
    {
        $producto = $this->createProducto();
        $productoId = $producto->getId();

        $this->client->request('DELETE', '/api/producto/' . $productoId);

        $this->assertResponseStatusCodeSame(204);

        // Verify the product was soft-deleted
        $this->entityManager->clear();
        $deletedProducto = $this->entityManager->getRepository(Producto::class)->find($productoId);
        $this->assertTrue($deletedProducto->isDeleted());
    }

    private function createProducto(string $nombre = 'Test Producto'): Producto
    {
        $producto = new Producto();
        $producto->setNombre($nombre);
        $producto->setSerial('SN-' . uniqid());
        $producto->setMarca('Marca Test');
        $producto->setModelo('Modelo Test');
        $producto->setSede('Sede Test');
        $producto->setOficina('Oficina Test');
        $producto->setDeleted(false);

        $this->entityManager->persist($producto);
        $this->entityManager->flush();

        return $producto;
    }

    protected function runCommand(string $command, array $params = []): int
    {
        $application = new Application(static::$kernel);
        $application->setAutoExit(false);

        $input = new ArrayInput(array_merge(['command' => $command], $params));
        $input->setInteractive(false);
        
        $output = new BufferedOutput();
        return $application->run($input, $output);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->entityManager->close();
        $this->entityManager = null; // avoid memory leaks
    }
}
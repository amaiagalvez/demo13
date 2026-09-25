<?php

namespace Tests\Unit\Customers;

use Tests\TestCase;

class CustomerTranslationTest extends TestCase
{
  public function test_customer_labels_are_available_in_all_supported_locales(): void
  {
    $expected = [
      'eu' => ['Bezeroak', 'Bezero berria', 'Editatu bezeroa', ':attribute eremua nahitaezkoa da.'],
      'es' => ['Clientes', 'Nuevo cliente', 'Editar cliente', 'El campo :attribute es obligatorio.'],
      'fr' => ['Clients', 'Nouveau client', 'Modifier le client', 'Le champ :attribute est obligatoire.'],
      'en' => ['Customers', 'New customer', 'Edit customer', 'The :attribute field is required.'],
    ];

    foreach ($expected as $locale => [$customers, $newCustomer, $editCustomer, $required]) {
      app()->setLocale($locale);

      $this->assertSame($customers, __('Customers'));
      $this->assertSame($newCustomer, __('New customer'));
      $this->assertSame($editCustomer, __('Edit customer'));
      $this->assertSame($required, __('validation.required'));
    }
  }

  public function test_basque_is_the_default_customer_locale(): void
  {
    app()->setLocale('eu');

    $this->assertSame('Bezeroak', __('Customers'));
    $this->assertSame('Bezero berria', __('New customer'));
    $this->assertSame('Editatu bezeroa', __('Edit customer'));
  }

  public function test_customer_success_messages_are_translated(): void
  {
    app()->setLocale('eu');

    $this->assertSame('Bezeroa behar bezala sortu da.', __('Customer created successfully.'));
    $this->assertSame('Bezeroa behar bezala eguneratu da.', __('Customer updated successfully.'));
    $this->assertSame('Bezeroa behar bezala ezabatu da.', __('Customer deleted successfully.'));
  }
}

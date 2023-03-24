# Output Normalizer

This library allows you to flexibly customize the output of your objects and entities to an array or JSON by defining normalization rules in classes that implements `OutputInterface`.

## Table of Contents

1. [Basic Example](#basic-example)
2. [How the Output Normalizer Works](#how-the-output-normalizer-works)
3. [Using Getters](#using-getters)
4. [Using Setters](#using-setters)
5. [Object Normalization](#object-normalization)
6. [Global Description of Object Normalization](#global-description-of-object-normalization)
7. [Entity Identifier Output](#entity-identifier-output)
8. [Using Output Modifiers](#using-output-modifiers)

## Basic Example

Let's imagine that we have a model or Doctrine entity like this:

```php
class User
{
    private int $id = 1;

    private string $userName = 'John Doe';

    private string $skippedProperty = 'Lorem Ipsum';

    public function getId(): int
    {
        return $this->id;
    }

    public function getUserName(): string
    {
        return $this->userName;
    }

    public function getSkippedProperty(): string
    {
        return $this->skippedProperty;
    }
}
```

We want to give only two properties to the output: `id` and `userName`. Let's describe our class:

```php
use Alexanevsky\OutputNormalizerBundle\Output\OutputInterface;

class UserOutput implements OutputInterface
{
    public int $id;

    public string $userName;
}
```

Add the `OutputNormalizer` to the constructor of controller or service:

```php
use Alexanevsky\OutputNormalizerBundle\OutputNormalizer;

public function __construct(
    private OutputNormalizer $outputNormalizer
) {}
```

And then we call the normalize method, passing in `User` entity and the `UserOutput` class name:

```php
$output = $this->outputNormalizer->normalize($user, UserOutput::class);
```

As a result, in `$output` we will get this:

```php
['id' => 1, 'user_name' => 'John Doe']
```

Note: Please note that property names are translated in snake case.

This is the basic functionality of the normalizer. We will explore its capabilities in more detail below.

## How the Output Normalizer Works

1. The normalizer takes the public properties and getters of your model (entity) and maps them to the public properties and setters of the output object.
2. The normalizer takes the public properties and getters of the output object and converts them into an array, changing the keys to snake case.
3. The normalizer invokes the call modifier.

First, the normalizer goes through the public properties, then through the getters (or setters, depending on the action).

*Note: If a public property has a getter (or setter), it will take precedence, i.e. the value of the getter will be taken (passed to the setter) rather than taken from the property (rather than assigned to the property). You can see more about how getters and setters are used in the library [alexanevsky/getter-setter-accessor](https://github.com/alexanevsky/getter-setter-accessor).*

## Using Getters

Let's imagine this model:

```php
class User
{
    private string $phone = '8002752273';

    public function getPhone(): string
    {
        return $this->string;
    }
}
```

To just output the phone, we just need to add this property to our Output class:

```php
class UserOutput implements OutputInterface
{
    public string $phone;
}
```

And our result will be like this:

```php
['phone' => '8002752273']
```

However, if we add a phone getter to our output that will perform some modification, it will be called during normalization:

```php
class UserOutput implements OutputInterface
{
    public string $phone;

    public function getPhone(): string
    {
        return '+1' . $this->phone;
    }
}
```

The result will be:

```php
['phone' => '+18002752273']
```

## Using Setters

Instead of using public properties in our output, we can use setters and getters as we normally do elsewhere in our project. Everything will work as it should:


```php
use Alexanevsky\OutputNormalizerBundle\Output\OutputInterface;

class UserOutput implements OutputInterface
{
    private int $id;

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getId(): int
    {
        return $this->id;
    }
}
```

## Object Normalization

Imagine that for the phone we use not a string, but a class:

```php
class Phone
{
    private string $prefix = '+1';

    private string $number = '8002752273';

    private string $country = 'USA';

    // Getters and setters of prefix, number and country...
}
```

And in the user model:

```php
class User
{
    private Phone $phone;
}
```

In the output, we must also use this model:

```php
class UserOutput implements OutputInterface
{
    public Phone $phone;
}
```

The object will be normalized by its public properties and getters, as Symphony Normalizer usually does:

```php
['phone' => ['prefix' => '+1', 'number' => '8002752273', 'country' => 'USA']]
```

We can also use `PhoneOutput` instead of `Phone`, which also implements `OutputInterface`, in which we define only the properties we need:
```php
class PhoneOutput implements OutputInterface
{
    private string $prefix = '+1';

    private string $number = '8002752273';
}

class UserOutput implements OutputInterface
{
    public PhoneOutput $phone;
}
```

As a result, we will get only the properties we need:

```php
['phone' => ['prefix' => '+1', 'number' => '8002752273']]
```

However, remember that we can use a getter, as in the example above, to output a modified result:

```php
class UserOutput implements OutputInterface
{
    public Phone $phone;

    public function getPhone(): string
    {
        return $this->phone->getPrefix() . $this->phone->getNumber();
    }
}
```

The result will be:

```php
['phone' => '+18002752273']
```

## Global Description of Object Normalization

We can globally describe how we want to normalize some objects. To do this, we need to create a class that inherits from `ObjectNormalizerInterface` and put it anywhere in our project:

```php
use Alexanevsky\OutputNormalizerBundle\ObjectNormalizer\ObjectNormalizerInterface;

class PhoneNormalizer implements ObjectNormalizerInterface
{
    public function supports(object $object): bool
    {
        return $object instanceof Phone;
    }

    public function normalize(object $object): string
    {
        return $phone->getPrefix() . $phone->getNumber();
    }
}
```

Now we can just specify `Phone` in our output and it will be normalized according to the rule above:

```php
class UserOutput implements OutputInterface
{
    public Phone $phone;
}
```

We will get this result:

```php
['phone' => '+18002752273']
```

## Entity Identifier Output

Imagine that our entity has a property with another entity:

```php
class City
{
    private int $id = 1;

    private string $name = 'Los Angeles';

    // Getters and setters of id and name...
}

class User
{
    private City $city;

    public function getCity(): City
    {
        return $this->city;
    }
}
```

In order not to normalize all properties of the City, but only its identifier, we must add the `EntityToId` attribute:

```php
use Alexanevsky\OutputNormalizerBundle\Output\Attribute\EntityToId;

class UserOutput implements OutputInterface
{
    #[EntityToId]
    public City $city;
}
```

And in the result we get this:

```php
['city_id' => 1]
```

If we have a many-to-one relationship in our model, we can display all identifiers in the same way:

```php
class User
{
    private Collection $cities;

    public function getCities(): Collection
    {
        return $this->cities;
    }
}

class UserOutput implements OutputInterface
{
    #[EntityToId]
    public Collection|array $cities;
}
```

The output will have `s` appended to the key, since we are outputting a set:

```php
['cities_ids' => [1]]
```

If the entity identifier is different from `id`, it must be passed as the first parameter of `EntityToId`:

```php
class Airport
{
    private string $code = 'LAX';

    private string $name = 'Los Angeles';

    // Getters and setters of code and name...
}

class User
{
    private Airport $airport;

    public function getAirport(): Airport
    {
        return $this->airport;
    }
}

class UserOutput implements OutputInterface
{
    #[EntityToId('code')]
    public Airport $airport;
}
```

Our result will be:

```php
['airport_code' => 'LAX']
```

If we want to override the suffix added to the output array key, we must pass it as the second parameter to `EntityToId`:

```php
class UserOutput implements OutputInterface
{
    #[EntityToId('code', 'identifier')]
    public Airport $airport;
}
```

Our result will be:

```php
['airport_identifier' => 'LAX']
```

If we don't want to add a suffix at all, we must pass `false` as the second parameter to `EntityToId`:

```php
class UserOutput implements OutputInterface
{
    #[EntityToId('code', false)]
    public Airport $airport;
}
```

Our result will be:

```php
['airport' => 'LAX']
```

## Using Output Modifiers

If we want to somehow change the data we normalize into an array, we must use `OutputModifierInterface`. It modifies our `OutputInterface` data.

Let's imagine the following entity:

```php
class User
{
    private array $roles = ['ROLE_USER', 'ROLE_ADMIN'];

    // Getter and setter of roles
}

class UserOutput implements OutputInterface
{
    public array $roles;
}
```

Let's say we want to remove the `ROLE_USER` role from the output and print all roles except for it. We can use `OutputModifierInterface` for this. Just put it anywhere in your project:

```php
use Alexanevsky\OutputNormalizerBundle\OutputModifier\OutputModifierInterface;

class UserOutputModifier extends OutputModifierInterface
{
    public function supports(object $output, object $source): bool
    {
        return $output instanceof UserOutput;
    }

    /**
     * @param UserOutput $output;
     */
    public function modify(OutputInterface $output, object $source): void
    {
        $output->roles = array_diff($output->roles, ['ROLE_USER']);
    }
}
```

The normalizer itself invokes modifiers and uses those that return `true` in the `supports` method for the model and output class passed to the normalizer.

Our output will be like this:

```php
['roles' => ['ROLE_ADMIN']]
```

Good luck!

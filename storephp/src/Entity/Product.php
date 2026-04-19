<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $title = '';

    #[ORM\Column(length: 255, unique: true)]
    private string $slug = '';

    #[ORM\Column(type: 'text')]
    private string $description = '';

    #[ORM\Column]
    private int $priceSaleCents = 0;

    #[ORM\Column]
    private int $priceBaseCents = 0;

    #[ORM\Column(length: 1024)]
    private string $referenceImageUrl = '';

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $imageUrls = null;

    #[ORM\Column(length: 255)]
    private string $category = '';

    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $variants = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    /**
     * @var Collection<int, OrderItem>
     */
    #[ORM\OneToMany(mappedBy: 'product', targetEntity: OrderItem::class)]
    private Collection $orderItems;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->orderItems = new ArrayCollection();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;
        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getPriceSaleCents(): int
    {
        return $this->priceSaleCents;
    }

    public function setPriceSaleCents(int $priceSaleCents): self
    {
        $this->priceSaleCents = $priceSaleCents;
        return $this;
    }

    public function getPriceBaseCents(): int
    {
        return $this->priceBaseCents;
    }

    public function setPriceBaseCents(int $priceBaseCents): self
    {
        $this->priceBaseCents = $priceBaseCents;
        return $this;
    }

    public function getReferenceImageUrl(): string
    {
        return $this->referenceImageUrl;
    }

    public function setReferenceImageUrl(string $referenceImageUrl): self
    {
        $this->referenceImageUrl = $referenceImageUrl;
        return $this;
    }

    /**
     * @return list<string>
     */
    public function getImageUrls(): array
    {
        $urls = $this->imageUrls ?? [];
        if ($this->referenceImageUrl && !in_array($this->referenceImageUrl, $urls, true)) {
            array_unshift($urls, $this->referenceImageUrl);
        }
        return array_values(array_unique($urls));
    }

    /**
     * @param list<string>|null $imageUrls
     */
    public function setImageUrls(?array $imageUrls): self
    {
        $this->imageUrls = $imageUrls;
        return $this;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function setCategory(string $category): self
    {
        $this->category = $category;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;
        return $this;
    }

    public function getVariants(): ?array
    {
        return $this->variants;
    }

    public function setVariants(?array $variants): self
    {
        $this->variants = $variants;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function touchUpdatedAt(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * @return Collection<int, OrderItem>
     */
    public function getOrderItems(): Collection
    {
        return $this->orderItems;
    }

    public function getOrdersCount(): int
    {
        return $this->orderItems->count();
    }
}

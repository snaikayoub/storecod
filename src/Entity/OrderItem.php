<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class OrderItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Order $order = null;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'orderItems')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Product $product = null;

    #[ORM\Column(length: 255)]
    private string $titleSnapshot = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $variantSnapshot = null;

    #[ORM\Column]
    private int $priceCentsSnapshot = 0;

    #[ORM\Column]
    private int $quantity = 1;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrder(): ?Order
    {
        return $this->order;
    }

    public function setOrder(?Order $order): self
    {
        $this->order = $order;
        return $this;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): self
    {
        $this->product = $product;
        return $this;
    }

    public function getTitleSnapshot(): string
    {
        return $this->titleSnapshot;
    }

    public function setTitleSnapshot(string $titleSnapshot): self
    {
        $this->titleSnapshot = $titleSnapshot;
        return $this;
    }

    public function getVariantSnapshot(): ?string
    {
        return $this->variantSnapshot;
    }

    public function setVariantSnapshot(?string $variantSnapshot): self
    {
        $this->variantSnapshot = $variantSnapshot;
        return $this;
    }

    public function getPriceCentsSnapshot(): int
    {
        return $this->priceCentsSnapshot;
    }

    public function setPriceCentsSnapshot(int $priceCentsSnapshot): self
    {
        $this->priceCentsSnapshot = $priceCentsSnapshot;
        return $this;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): self
    {
        $this->quantity = $quantity;
        return $this;
    }
}

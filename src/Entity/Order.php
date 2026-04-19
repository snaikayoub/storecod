<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'orders')]
class Order
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $customerName = '';

    #[ORM\Column(length: 255)]
    private string $customerPhone = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $customerEmail = null;

    #[ORM\Column(length: 255)]
    private string $customerCity = '';

    #[ORM\Column(type: 'text')]
    private string $customerAddress = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(length: 32)]
    private string $status = 'pending';

    #[ORM\Column]
    private int $totalCents = 0;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /**
     * @var Collection<int, OrderItem>
     */
    #[ORM\OneToMany(mappedBy: 'order', targetEntity: OrderItem::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $items;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->items = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCustomerName(): string
    {
        return $this->customerName;
    }

    public function setCustomerName(string $customerName): self
    {
        $this->customerName = $customerName;
        return $this;
    }

    public function getCustomerPhone(): string
    {
        return $this->customerPhone;
    }

    public function setCustomerPhone(string $customerPhone): self
    {
        $this->customerPhone = $customerPhone;
        return $this;
    }

    public function getCustomerEmail(): ?string
    {
        return $this->customerEmail;
    }

    public function setCustomerEmail(?string $customerEmail): self
    {
        $this->customerEmail = $customerEmail;
        return $this;
    }

    public function getCustomerCity(): string
    {
        return $this->customerCity;
    }

    public function setCustomerCity(string $customerCity): self
    {
        $this->customerCity = $customerCity;
        return $this;
    }

    public function getCustomerAddress(): string
    {
        return $this->customerAddress;
    }

    public function setCustomerAddress(string $customerAddress): self
    {
        $this->customerAddress = $customerAddress;
        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): self
    {
        $this->comment = $comment;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getTotalCents(): int
    {
        return $this->totalCents;
    }

    public function setTotalCents(int $totalCents): self
    {
        $this->totalCents = $totalCents;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return Collection<int, OrderItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(OrderItem $item): self
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setOrder($this);
        }
        return $this;
    }

    public function getItemsAdminSummary(): string
    {
        $lines = [];
        foreach ($this->items as $it) {
            if (!$it instanceof OrderItem) {
                continue;
            }

            $title = trim($it->getTitleSnapshot());
            $variant = $it->getVariantSnapshot();
            $variant = is_string($variant) ? trim($variant) : '';
            $qty = $it->getQuantity();

            $label = $title !== '' ? $title : 'Produit';
            if ($variant !== '') {
                $label .= ' (' . $variant . ')';
            }

            $unitCents = $it->getPriceCentsSnapshot();
            $line = $label . ' x' . $qty;
            if ($unitCents > 0) {
                $line .= ' @ ' . number_format($unitCents / 100, 2, '.', '') . ' DH';
            }
            $lines[] = $line;
        }

        return implode("\n", $lines);
    }

    public function getItemsProductsList(): string
    {
        $out = [];
        foreach ($this->items as $it) {
            if (!$it instanceof OrderItem) {
                continue;
            }
            $t = trim($it->getTitleSnapshot());
            $out[] = $t !== '' ? $t : 'Produit';
        }
        return implode("\n", $out);
    }

    public function getItemsVariantsList(): string
    {
        $out = [];
        foreach ($this->items as $it) {
            if (!$it instanceof OrderItem) {
                continue;
            }
            $v = $it->getVariantSnapshot();
            $v = is_string($v) ? trim($v) : '';
            $out[] = $v !== '' ? $v : '-';
        }
        return implode("\n", $out);
    }

    public function getItemsQuantitiesList(): string
    {
        $out = [];
        foreach ($this->items as $it) {
            if (!$it instanceof OrderItem) {
                continue;
            }
            $out[] = (string) $it->getQuantity();
        }
        return implode("\n", $out);
    }

    public function getItemsColorsList(): string
    {
        $out = [];
        foreach ($this->items as $it) {
            if (!$it instanceof OrderItem) {
                continue;
            }

            $hay = $it->getTitleSnapshot();
            $v = $it->getVariantSnapshot();
            if (is_string($v) && trim($v) !== '') {
                $hay .= ' ' . $v;
            }

            $c = $this->guessColor($hay);
            $out[] = $c ?? '-';
        }
        return implode("\n", $out);
    }

    /**
     * @return list<string>
     */
    public function getItemsThumbUrls(): array
    {
        $out = [];
        foreach ($this->items as $it) {
            if (!$it instanceof OrderItem) {
                continue;
            }
            $p = $it->getProduct();
            if (!$p) {
                continue;
            }
            $u = trim($p->getPrimaryImageUrl());
            if ($u !== '') {
                $out[] = $u;
            }
        }

        return array_values(array_unique($out));
    }

    private function guessColor(string $text): ?string
    {
        $t = mb_strtolower($text);

        $colors = [
            'noir' => 'Noir',
            'blanc' => 'Blanc',
            'bleu ciel' => 'Bleu Ciel',
            'bleu' => 'Bleu',
            'vert olive' => 'Vert Olive',
            'olive' => 'Olive',
            'vert' => 'Vert',
            'beige' => 'Beige',
            'gris' => 'Gris',
            'marron' => 'Marron',
            'rouge' => 'Rouge',
            'bordeau' => 'Bordeau',
            'bordeaux' => 'Bordeaux',
            'camel' => 'Camel',
            'marine' => 'Marine',
        ];

        foreach ($colors as $needle => $label) {
            if (str_contains($t, $needle)) {
                return $label;
            }
        }

        return null;
    }
}

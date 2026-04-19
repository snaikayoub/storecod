<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\HttpFoundation\File\UploadedFile;

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

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $descriptionFr = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $descriptionAr = null;

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

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $promoTiers = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    /**
     * @var Collection<int, OrderItem>
     */
    #[ORM\OneToMany(mappedBy: 'product', targetEntity: OrderItem::class)]
    private Collection $orderItems;

    /**
     * @var Collection<int, ProductVariant>
     */
    #[ORM\OneToMany(mappedBy: 'product', targetEntity: ProductVariant::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $variants2;

    /**
     * @var Collection<int, ProductMedia>
     */
    #[ORM\OneToMany(mappedBy: 'product', targetEntity: ProductMedia::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $medias;

    /**
     * @var list<UploadedFile>
     */
    private array $mediaUploads = [];

    private ?UploadedFile $videoUpload = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->orderItems = new ArrayCollection();
        $this->variants2 = new ArrayCollection();
        $this->medias = new ArrayCollection();
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

    public function getDescriptionFr(): string
    {
        $v = is_string($this->descriptionFr) ? trim($this->descriptionFr) : '';
        if ($v !== '') {
            return $this->descriptionFr;
        }

        return $this->description;
    }

    public function setDescriptionFr(?string $descriptionFr): self
    {
        $this->descriptionFr = $descriptionFr;
        return $this;
    }

    public function getDescriptionAr(): string
    {
        return is_string($this->descriptionAr) ? $this->descriptionAr : '';
    }

    public function setDescriptionAr(?string $descriptionAr): self
    {
        $this->descriptionAr = $descriptionAr;
        return $this;
    }

    public function getDescriptionForLocale(string $locale): string
    {
        $locale = strtolower(trim($locale));
        $isAr = str_starts_with($locale, 'ar');

        if ($isAr) {
            $ar = trim($this->getDescriptionAr());
            if ($ar !== '') {
                return $ar;
            }

            return $this->getDescriptionFr();
        }

        return $this->getDescriptionFr();
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

    public function getPrimaryImageUrl(): string
    {
        foreach ($this->medias as $m) {
            if ($m instanceof ProductMedia && $m->isPrimary() && $m->getKind() === 'image') {
                return $m->getUrl();
            }
        }

        foreach ($this->medias as $m) {
            if ($m instanceof ProductMedia && $m->getKind() === 'image') {
                return $m->getUrl();
            }
        }

        return $this->referenceImageUrl;
    }

    /**
     * @return list<string>
     */
    public function getMediaImageUrls(): array
    {
        $out = [];
        foreach ($this->medias as $m) {
            if ($m instanceof ProductMedia && $m->getKind() === 'image') {
                $u = trim($m->getUrl());
                if ($u !== '') {
                    $out[] = $u;
                }
            }
        }
        return array_values(array_unique($out));
    }

    /**
     * @return list<UploadedFile>
     */
    public function getMediaUploads(): array
    {
        return $this->mediaUploads;
    }

    /**
     * @param list<UploadedFile>|null $mediaUploads
     */
    public function setMediaUploads(?array $mediaUploads): self
    {
        $this->mediaUploads = [];
        if (is_array($mediaUploads)) {
            foreach ($mediaUploads as $f) {
                if ($f instanceof UploadedFile) {
                    $this->mediaUploads[] = $f;
                }
            }
        }

        return $this;
    }

    public function getVideoUpload(): ?UploadedFile
    {
        return $this->videoUpload;
    }

    public function setVideoUpload(?UploadedFile $videoUpload): self
    {
        $this->videoUpload = $videoUpload;
        return $this;
    }

    /**
     * @return list<array{id:int,kind:string,url:string,primary:bool}>
     */
    public function getMediaAdminItems(): array
    {
        $out = [];
        foreach ($this->medias as $m) {
            if (!$m instanceof ProductMedia) {
                continue;
            }
            $out[] = [
                'id' => (int) $m->getId(),
                'kind' => $m->getKind(),
                'url' => $m->getUrl(),
                'primary' => (bool) $m->isPrimary(),
            ];
        }
        usort($out, static function (array $a, array $b): int {
            if ($a['kind'] !== $b['kind']) {
                return $a['kind'] === 'video' ? -1 : 1;
            }
            if ($a['primary'] !== $b['primary']) {
                return $a['primary'] ? -1 : 1;
            }
            return ($a['id'] <=> $b['id']);
        });
        return $out;
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

    public function getImageUrlsEditor(): string
    {
        $urls = $this->imageUrls ?? [];
        $lines = [];
        foreach ($urls as $v) {
            if (!is_string($v)) {
                continue;
            }
            $v = trim($v);
            if ($v !== '') {
                $lines[] = $v;
            }
        }

        return implode("\n", array_values(array_unique($lines)));
    }

    public function setImageUrlsEditor(?string $value): self
    {
        $value = is_string($value) ? $value : '';
        $value = trim($value);
        if ($value === '') {
            $this->imageUrls = null;
            return $this;
        }

        $out = [];
        foreach (preg_split('/\R/', $value) ?: [] as $line) {
            $line = trim((string) $line);
            if ($line !== '') {
                $out[] = $line;
            }
        }

        $this->imageUrls = $out !== [] ? array_values(array_unique($out)) : null;

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

    public function getVariantsEditor(): string
    {
        if (!is_array($this->variants) || $this->variants === []) {
            return '';
        }

        $json = json_encode($this->variants, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        return is_string($json) ? $json : '';
    }

    public function setVariantsEditor(?string $value): self
    {
        $value = is_string($value) ? trim($value) : '';
        if ($value === '') {
            $this->variants = null;
            return $this;
        }

        $decoded = json_decode($value, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this;
        }

        if (!is_array($decoded)) {
            return $this;
        }

        $this->variants = $decoded;
        return $this;
    }

    /**
     * @return list<string>
     */
    public function getVariantOptions(): array
    {
        $out = [];

        foreach ($this->variants2 as $v) {
            if ($v instanceof ProductVariant && $v->isActive()) {
                $label = trim($v->getLabel());
                if ($label !== '') {
                    $out[] = $label;
                }
            }
        }

        $variants = $this->variants;
        if (is_array($variants)) {
            foreach ($variants as $v) {
                if (!is_string($v)) {
                    continue;
                }
                $v = trim($v);
                if ($v !== '') {
                    $out[] = $v;
                }
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @return Collection<int, ProductVariant>
     */
    public function getVariants2(): Collection
    {
        return $this->variants2;
    }

    /**
     * @return Collection<int, ProductMedia>
     */
    public function getMedias(): Collection
    {
        return $this->medias;
    }

    public function getPromoTiersEditor(): string
    {
        if (!is_array($this->promoTiers) || $this->promoTiers === []) {
            return '';
        }
        $json = json_encode($this->promoTiers, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        return is_string($json) ? $json : '';
    }

    public function setPromoTiersEditor(?string $value): self
    {
        $value = is_string($value) ? trim($value) : '';
        if ($value === '') {
            $this->promoTiers = null;
            return $this;
        }

        $decoded = json_decode($value, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this;
        }
        if (!is_array($decoded)) {
            return $this;
        }

        $this->promoTiers = $decoded;
        return $this;
    }

    /**
     * @return list<array{qty:int,totalCents:int}>
     */
    public function getPromoTiersNormalized(): array
    {
        if (!is_array($this->promoTiers) || $this->promoTiers === []) {
            return [];
        }

        $out = [];
        foreach ($this->promoTiers as $row) {
            if (!is_array($row)) {
                continue;
            }
            $qty = (int) ($row['qty'] ?? 0);
            $totalCents = (int) ($row['totalCents'] ?? 0);
            if ($qty > 0 && $totalCents > 0) {
                $out[] = ['qty' => $qty, 'totalCents' => $totalCents];
            }
        }

        usort($out, static fn (array $a, array $b): int => $a['qty'] <=> $b['qty']);
        return $out;
    }

    public function getTotalForQuantityCents(int $qty): int
    {
        $qty = max(1, $qty);
        $tiers = $this->getPromoTiersNormalized();

        // Use dynamic programming to find the best combination of promo tiers
        // for the *exact* requested quantity. This avoids returning the 3-pack
        // price when ordering 4+ items.
        $options = [];
        $options[] = ['qty' => 1, 'totalCents' => (int) $this->priceSaleCents];
        foreach ($tiers as $t) {
            $options[] = ['qty' => (int) $t['qty'], 'totalCents' => (int) $t['totalCents']];
        }

        $inf = (int) (PHP_INT_MAX / 8);
        $dp = array_fill(0, $qty + 1, $inf);
        $dp[0] = 0;

        for ($i = 1; $i <= $qty; $i++) {
            $best = $inf;
            foreach ($options as $opt) {
                $q = (int) ($opt['qty'] ?? 0);
                $c = (int) ($opt['totalCents'] ?? 0);
                if ($q <= 0 || $i < $q) {
                    continue;
                }
                $prev = $dp[$i - $q];
                if ($prev === $inf) {
                    continue;
                }
                $candidate = $prev + $c;
                if ($candidate < $best) {
                    $best = $candidate;
                }
            }
            $dp[$i] = $best;
        }

        if ($dp[$qty] !== $inf) {
            return (int) $dp[$qty];
        }

        // Fallback: no tiers/unit price could form an exact total
        return (int) ($this->priceSaleCents * $qty);
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

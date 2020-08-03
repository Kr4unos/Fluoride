<?php

namespace App\Entity;

use App\Repository\AlbumRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=AlbumRepository::class)*
 * @ORM\Table(name="fl_music_album")
 */
class Album
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255, unique=true)
     */
    private $spotify_id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $name;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $cover;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $label;

    /**
     * @ORM\Column(type="date")
     */
    private $release_date;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $release_date_precision;

    /**
     * @ORM\Column(type="integer")
     */
    private $popularity;
    /**
     * @ORM\ManyToMany(targetEntity="Artist", inversedBy="albums", cascade={"persist"})
     * @ORM\OrderBy({"name" = "asc"})
     */
    private $artists;

    /**
     * @ORM\OneToMany(targetEntity="Track", mappedBy="album", cascade={"persist", "remove"})
     * @ORM\OrderBy({"disc_number" = "asc", "track_number" = "asc"})
     */
    private $tracks;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $album_type;

    public function __construct()
    {
        $this->artists = new ArrayCollection();
        $this->tracks = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAlbumType(): ?string
    {
        return $this->album_type;
    }

    public function setAlbumType(string $album_type): self
    {
        $this->album_type = $album_type;

        return $this;
    }

    /**
     * @return Collection|Artist[]
     */
    public function getArtists(): Collection
    {
        return $this->artists;
    }

    public function addArtist(Artist $artist): self
    {
        if (!$this->artists->contains($artist)) {
            $this->artists[] = $artist;
        }

        return $this;
    }

    public function removeArtist(Artist $artist): self
    {
        if ($this->artists->contains($artist)) {
            $this->artists->removeElement($artist);
        }

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getCover(): ?string
    {
        return $this->cover;
    }

    public function setCover(string $cover): self
    {
        $this->cover = $cover;

        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function getPopularity(): ?int
    {
        return $this->popularity;
    }

    public function setPopularity(int $popularity): self
    {
        $this->popularity = $popularity;

        return $this;
    }

    /**
     * @return Collection|Track[]
     */
    public function getTracks(): Collection
    {
        return $this->tracks;
    }

    public function addTrack(Track $track): self
    {
        if (!$this->tracks->contains($track)) {
            $this->tracks[] = $track;
            $track->setAlbum($this);
        }

        return $this;
    }

    public function removeTrack(Track $track): self
    {
        if ($this->tracks->contains($track)) {
            $this->tracks->removeElement($track);
            // set the owning side to null (unless already changed)
            if ($track->getAlbum() === $this) {
                $track->setAlbum(null);
            }
        }

        return $this;
    }

    public function getSpotifyId(): ?string
    {
        return $this->spotify_id;
    }

    public function setSpotifyId(string $spotify_id): self
    {
        $this->spotify_id = $spotify_id;

        return $this;
    }

    public function getReleaseDatePrecision(): ?string
    {
        return $this->release_date_precision;
    }

    public function setReleaseDatePrecision(string $release_date_precision): self
    {
        $this->release_date_precision = $release_date_precision;

        return $this;
    }

    public function getReleaseDate(): ?\DateTimeInterface
    {
        return $this->release_date;
    }

    public function setReleaseDate(\DateTimeInterface $release_date): self
    {
        $this->release_date = $release_date;

        return $this;
    }
}

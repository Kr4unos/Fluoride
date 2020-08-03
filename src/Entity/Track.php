<?php

namespace App\Entity;

use App\Repository\TrackRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=TrackRepository::class)
 * @ORM\Table(name="fl_music_track")
 */
class Track
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="Album", inversedBy="tracks")
     */
    private $album;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $name;

    /**
     * @ORM\Column(type="string", length=255, unique=true)
     */
    private $spotify_id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $track_type;

    /**
    * @ORM\Column(type="integer")
     */
    private $disc_number;

    /**
     * @ORM\Column(type="integer")
     */
    private $track_number;

    /**
     * @ORM\Column(type="integer")
     */
    private $duration_ms;

    /**
     * @ORM\Column(type="boolean")
     */
    private $explicit;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDiscNumber(): ?int
    {
        return $this->disc_number;
    }

    public function setDiscNumber(int $disc_number): self
    {
        $this->disc_number = $disc_number;

        return $this;
    }

    public function getTrackNumber(): ?int
    {
        return $this->track_number;
    }

    public function setTrackNumber(int $track_number): self
    {
        $this->track_number = $track_number;

        return $this;
    }

    public function getDurationMs(): ?int
    {
        return $this->duration_ms;
    }

    public function setDurationMs(int $duration_ms): self
    {
        $this->duration_ms = $duration_ms;

        return $this;
    }

    public function getExplicit(): ?bool
    {
        return $this->explicit;
    }

    public function setExplicit(bool $explicit): self
    {
        $this->explicit = $explicit;

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

    public function getAlbum(): ?Album
    {
        return $this->album;
    }

    public function setAlbum(?Album $album): self
    {
        $this->album = $album;

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

    public function getTrackType(): ?string
    {
        return $this->track_type;
    }

    public function setTrackType(string $track_type): self
    {
        $this->track_type = $track_type;

        return $this;
    }
}

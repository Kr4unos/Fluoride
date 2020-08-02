<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\TVEpisodeRepository")
 * @ORM\Table(name="fl_tvepisode")
 */
class TVEpisode
{
    /**
     * @ORM\Id()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="TVSeason", inversedBy="episodes")
     */
    private $season;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $title;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $original_title;

    /**
     * @ORM\Column(type="integer")
     */
    private $episode_number;

    /**
     * @ORM\Column(type="text")
     */
    private $overview;

    /**
     * @ORM\Column(type="date")
     */
    private $air_date;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $rating;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $poster;

    /**
     * @ORM\Column(type="boolean")
     */
    private $downloaded;

    /**
     * @ORM\Column(type="boolean")
     */
    private $seen;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getOriginalTitle(): ?string
    {
        return $this->original_title;
    }

    public function setOriginalTitle(string $original_title): self
    {
        $this->original_title = $original_title;

        return $this;
    }

    public function getEpisodeNumber(): ?int
    {
        return $this->episode_number;
    }

    public function setEpisodeNumber(int $episode_number): self
    {
        $this->episode_number = $episode_number;

        return $this;
    }

    public function getOverview(): ?string
    {
        return $this->overview;
    }

    public function setOverview(string $overview): self
    {
        $this->overview = $overview;

        return $this;
    }

    public function getAirDate(): ?\DateTimeInterface
    {
        return $this->air_date;
    }

    public function setAirDate(\DateTimeInterface $air_date): self
    {
        $this->air_date = $air_date;

        return $this;
    }

    public function getRating(): ?string
    {
        return $this->rating;
    }

    public function setRating(string $rating): self
    {
        $this->rating = $rating;

        return $this;
    }

    public function getPoster(): ?string
    {
        return $this->poster;
    }

    public function setPoster(string $poster): self
    {
        $this->poster = $poster;

        return $this;
    }

    public function getDownloaded(): ?bool
    {
        return $this->downloaded;
    }

    public function setDownloaded(bool $downloaded): self
    {
        $this->downloaded = $downloaded;

        return $this;
    }

    public function getSeen(): ?bool
    {
        return $this->seen;
    }

    public function setSeen(bool $seen): self
    {
        $this->seen = $seen;

        return $this;
    }

    public function getSeason(): ?TVSeason
    {
        return $this->season;
    }

    public function setSeason(?TVSeason $season): self
    {
        $this->season = $season;

        return $this;
    }
}

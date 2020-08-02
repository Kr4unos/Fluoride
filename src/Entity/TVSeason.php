<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\TVSeasonRepository")
 * @ORM\Table(name="fl_tvseason")
 */
class TVSeason
{
    /**
     * @ORM\Id()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="TVShow", inversedBy="seasons")
     */
    private $tv_show;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $name;

    /**
     * @ORM\Column(type="date")
     */
    private $air_date;

    /**
     * @ORM\Column(type="text")
     */
    private $overview;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $poster;

    /**
     * @ORM\Column(type="integer")
     */
    private $season_number;

    /**
     * @ORM\OneToMany(targetEntity="TVEpisode", mappedBy="season", cascade={"persist"})
     * @ORM\OrderBy({"episode_number" = "asc"})
     */
    private $episodes;

    public function __construct()
    {
        $this->episodes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;

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

    public function getOverview(): ?string
    {
        return $this->overview;
    }

    public function setOverview(string $overview): self
    {
        $this->overview = $overview;

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

    public function getSeasonNumber(): ?int
    {
        return $this->season_number;
    }

    public function setSeasonNumber(int $season_number): self
    {
        $this->season_number = $season_number;

        return $this;
    }

    public function getTvShow(): ?TVShow
    {
        return $this->tv_show;
    }

    public function setTvShow(?TVShow $tv_show): self
    {
        $this->tv_show = $tv_show;

        return $this;
    }

    /**
     * @return Collection|TVEpisode[]
     */
    public function getEpisodes(): Collection
    {
        return $this->episodes;
    }

    public function addEpisode(TVEpisode $episode): self
    {
        if (!$this->episodes->contains($episode)) {
            $this->episodes[] = $episode;
            $episode->setSeason($this);
        }

        return $this;
    }

    public function removeEpisode(TVEpisode $episode): self
    {
        if ($this->episodes->contains($episode)) {
            $this->episodes->removeElement($episode);
            // set the owning side to null (unless already changed)
            if ($episode->getSeason() === $this) {
                $episode->setSeason(null);
            }
        }

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
}

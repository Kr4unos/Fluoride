<?php

namespace App\Entity;

use App\Repository\PeopleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=PeopleRepository::class)
 * @ORM\Table(name="fl_people")
 */
class People
{
    /**
     * @ORM\Id()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $name;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $nicknames;

    /**
     * @ORM\Column(type="text")
     */
    private $biography;

    /**
     * @ORM\Column(type="date", nullable=true)
     */
    private $birthday;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $place_of_birth;

    /**
     * @ORM\Column(type="date", nullable=true)
     */
    private $deathday;

    /**
     * @ORM\Column(type="boolean")
     */
    private $isAdultMovie;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $imdbId;

    /**
     * @ORM\Column(type="float")
     */
    private $popularity;

    /**
     * @ORM\Column(type="integer")
     */
    private $gender;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $profile;

    /**
     * @ORM\ManyToMany(targetEntity="Movie", mappedBy="peoples")
     * @ORM\JoinTable(name="fl_movie_people")
     * @ORM\OrderBy({"release_date" = "desc" })
     */
    private $movies;

    /**
     * @ORM\ManyToMany(targetEntity="TVShow", mappedBy="peoples")
     * @ORM\JoinTable(name="fl_tvshow_people")
     * @ORM\OrderBy({"release_date" = "desc" })
     */
    private $tv_shows;

    public function __construct()
    {
        $this->movies = new ArrayCollection();
        $this->tv_shows = new ArrayCollection();
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

    public function getIsAdultMovie(): ?bool
    {
        return $this->isAdultMovie;
    }

    public function setIsAdultMovie(bool $isAdultMovie): self
    {
        $this->isAdultMovie = $isAdultMovie;

        return $this;
    }

    public function getPopularity(): ?float
    {
        return $this->popularity;
    }

    public function setPopularity(float $popularity): self
    {
        $this->popularity = $popularity;

        return $this;
    }

    public function getGender(): ?int
    {
        return $this->gender;
    }

    public function setGender(int $gender): self
    {
        $this->gender = $gender;

        return $this;
    }

    public function getProfile(): ?string
    {
        return $this->profile;
    }

    public function setProfile(string $profile): self
    {
        $this->profile = $profile;

        return $this;
    }

    public function getNicknames(): ?string
    {
        return $this->nicknames;
    }

    public function setNicknames(string $nicknames): self
    {
        $this->nicknames = $nicknames;

        return $this;
    }

    public function getBiography(): ?string
    {
        return $this->biography;
    }

    public function setBiography(string $biography): self
    {
        $this->biography = $biography;

        return $this;
    }

    public function getBirthday(): ?\DateTimeInterface
    {
        return $this->birthday;
    }

    public function setBirthday(?\DateTimeInterface $birthday): self
    {
        $this->birthday = $birthday;

        return $this;
    }

    public function getDeathday(): ?\DateTimeInterface
    {
        return $this->deathday;
    }

    public function setDeathday(?\DateTimeInterface $deathday): self
    {
        $this->deathday = $deathday;

        return $this;
    }

    public function getImdbId(): ?string
    {
        return $this->imdbId;
    }

    public function setImdbId(string $imdbId): self
    {
        $this->imdbId = $imdbId;

        return $this;
    }

    public function getPlaceOfBirth(): ?string
    {
        return $this->place_of_birth;
    }

    public function setPlaceOfBirth(string $place_of_birth): self
    {
        $this->place_of_birth = $place_of_birth;

        return $this;
    }

    /**
     * @return Collection|Movie[]
     */
    public function getMovies(): Collection
    {
        return $this->movies;
    }

    public function addMovie(Movie $movie): self
    {
        if (!$this->movies->contains($movie)) {
            $this->movies[] = $movie;
            $movie->addPeople($this);
        }

        return $this;
    }

    public function removeMovie(Movie $movie): self
    {
        if ($this->movies->contains($movie)) {
            $this->movies->removeElement($movie);
            $movie->removePeople($this);
        }

        return $this;
    }

    /**
     * @return Collection|TVShow[]
     */
    public function getTvShows(): Collection
    {
        return $this->tv_shows;
    }

    public function addTvShow(TVShow $tvShow): self
    {
        if (!$this->tv_shows->contains($tvShow)) {
            $this->tv_shows[] = $tvShow;
            $tvShow->addPeople($this);
        }

        return $this;
    }

    public function removeTvShow(TVShow $tvShow): self
    {
        if ($this->tv_shows->contains($tvShow)) {
            $this->tv_shows->removeElement($tvShow);
            $tvShow->removePeople($this);
        }

        return $this;
    }
}

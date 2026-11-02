<?php

declare(strict_types=1);

namespace App\Command;

use App\Enum\Clan;
use App\Enum\Side;
use App\Repository\PrintingRepository;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

#[AsCommand(
    name: 'app:cards:rename',
    description: 'Rename cards from the scan dump to our ids',
)]
readonly class CardsRenameCommand
{
    public function __construct(
        #[Autowire(param: 'kernel.project_dir')]
        private string $projectDir,
        private PrintingRepository $printingRepository,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('Path')]
        string $path,
    ): int {
        $io->note(sprintf('The value of $path is: %s', var_export($path, true)));

        $path = rtrim($path, '/');

        $fs = new Filesystem();
        if (!$fs->exists($path)) {
            throw new \InvalidArgumentException("Path does not exist");
        }

        $dictionary = [];
        $printings = $this->printingRepository->findAll();
        foreach ($printings as $printing) {
            $card = $printing->getLogicalCard();
            $pack = $printing->getPack();
            $cardPosition = $printing->getPosition() or throw new \UnexpectedValueException('No card position');
            $packPosition = $pack->getPosition();
            $cyclePosition = $pack->getCycle()->getPosition();

            $directoryName = match ($cyclePosition) {
                1 => 'Core Set',
                2 => 'Clan Packs',
                3 => match ($packPosition) {
                    1 => 'Children of the Empire',
                    default => null,
                },
                4 => 'First cycle',
                5 => 'Second cycle',
                6 => 'Third cycle',
                7 => 'fourth cycle',
                8 => 'Fifth cycle',
                9 => 'Fifth cycle',
                default => null,
            };

            if ($directoryName === null) {
                continue;
            }

            $clanName = match ($card->getClan()) {
                Clan::Crab => 'Crab',
                Clan::Crane => 'Crane',
                Clan::Dragon => 'Dragon',
                Clan::Lion => 'Lion',
                Clan::Neutral => 'Neutral',
                Clan::Phoenix => match ($pack->getName()) {
                    'All and Nothing' => 'Phoenix',
                    'Tainted Lands' => 'Phoenix',
                    'Elements Unbound' => 'Phoenix',
                    'Breath of the Kami' => 'Phoenix',
                    'The Fires Within' => 'Phoenix',
                    default => 'Pheonix',
                },
                Clan::Scorpion => 'Scorpion',
                Clan::Unicorn => 'Unicorn',
                default => 'Neutral',
            };
            $sideName = match ($card->getSide()) {
                Side::Conflict => 'C',
                Side::Dynasty => 'D',
                Side::Province => 'Province',
                Side::Stronghold => 'Stronghold',
                Side::Role => 'Role',
            };

            $cardName = strtr($card->getName(), ['ō' => 'o', 'ū' => 'u', 'ü' => 'u']);
            $cardName = match ($cardName) {
                'Militant Faithful' => 'Military Faithful',
                'Talented Performer' => 'Talanted Performer',
                'Offerings to the Kami' => 'Offering to the Kami',
                'Sudden Tempest' => 'SuddenTempest',
                'Honored Veterans' => 'Honored Veteran',
                'Keeper of Secret Names' => 'Keepers of the Secret Names',
                'Akodo Kaede' => 'Akodao Kaede',
                'Utaku Mediator' => 'Utaki Mediator',
                'Blood of Onnotangu' => 'Blood of Onnatangu',
                'Seal of the Phoenix' => 'Seal of the Pheonix',
                'Northern Wall Sensei' => 'Northenrn Wall Sensei',
                'Mantra of Water' => 'Matra of Water',
                'Consumed by Five Fires' => 'Consumed by the Five Fires',
                'Kanjo District' => 'Kango District',
                'Embrace Death' => 'Embrance Death',
                'Ring of Binding' => 'Ring Binding',
                'Dragon Tattoo' => 'Dragon Tatto',
                'Mirumoto Masashige' => 'Mirumotu Masashige',
                'Distinguished Dojo' => 'Distinguished Djo',
                'Bayushi Aramoro' => 'Bayushi Aramona',
                'The Strength of the Mountain' => 'The Streght Of the Mountain',
                'Kuni Wastelands' => 'Kuni Wasteland',
                'Purifier Apprentice' => 'Purified Apprentice',
                'Teachings of the Elements' => 'Teaching of the Elements',
                'Kuni Laboratory' => 'Kuni Labratory',
                'Fu Sui Disciple' => 'Fu Sui Didciple',
                'Raging Battleground' => 'Raging Battlegroung',
                'Taryu-Jiai' => 'Tarju-Jiai',
                'Strength in Numbers' => 'Streght In Numbers',
                'Meddling Mediator' => 'Medding Mediatoe',
                'Magnificent Kimono' => 'Magnifecent Kimono',
                'Tattooed Wanderer' => 'Tattoed Wanderer',
                'Mantra of Fire' => 'Matra Of Fire',
                'Good Omen' => 'Cool Omen',
                'Ornate Fan' => 'Orrate Fan',
                'Assassination' => 'Assassiation',
                'Meditations on the Tao' => 'Meditation On The Tao',
                'Noble Sacrifice' => 'Nobel Sacrifice',
                'Political Rival' => 'Politica Rival',
                'The Perfect Gift' => 'The Perfet Gift',
                'Unassuming Yojimbo' => 'Anassuming Yojimbo',
                'Secret Cache' => 'Secret Cashe',
                'Forged Edict' => 'Forget Edict',
                'Calling in Favors' => 'Calling In Favours',
                'Defend the Wall' => 'Defent The Wall',
                'Hiruma Yojimbo' => 'Hiruma Jojimbo',
                'Hida Tomonatsu' => 'Hida Tomanatsu',
                'Kirei-ko' => 'Kirei -Ko',
                'Honed Nodachi' => 'Honoed Nodachi',
                'Paragon of Grace' => 'Paragon Of Crace',
                'The Fires of Justice' => 'The Fire Of Justice',
                default => $cardName,
            };

            $filename = sprintf(
                '%d_%s_%s_%s.webp',
                $cardPosition,
                $clanName,
                $sideName,
                $cardName
            );

            if ($cyclePosition === 2) {
                $filename = sprintf(
                    '%s/%s_%s_%d_%s.webp',
                    match ($packPosition) {
                        6 => 'Crab',
                        1 => 'Pheonix',
                        4 => 'Crane',
                        7 => 'Dragon',
                        5 => 'Lion',
                        2 => 'Scorpion',
                        3 => 'Unicorn',
                        default => throw new \UnexpectedValueException(sprintf('Unknown clan pack position %d', $packPosition)),
                    },
                    match ($clanName) {
                        'Pheonix' => match ($packPosition) {
                            5 => 'Phoenix',
                            1 => 'Phoenix',
                            2 => 'Phoenix',
                            6 => 'Phoenix',
                            default => $clanName,
                        },
                        default => $clanName,
                    },
                    $sideName,
                    $cardPosition,
                    $cardName
                );
            }

            if ($cyclePosition === 1) {
                $filename = sprintf(
                    '%s/%s_%s_%d_%s.webp',
                    $clanName,
                    match ($clanName) {
                        'Lion' => 'Lion ',
                        'Pheonix' => 'Phoenix',
                        default => $clanName,
                    },
                    $sideName,
                    $cardPosition,
                    $cardName
                );

                if ($card->getClan() === Clan::Phoenix) {
                    $filename = sprintf(
                        '%s/%d_%s_%s_%s_%s.webp',
                        $clanName,
                        $cardPosition,
                        'Phoenix',
                        $card->getType()->name,
                        $sideName,
                        $cardName
                    );

                    if ($card->getSide() === Side::Province || $card->getSide() === Side::Stronghold) {
                        $filename = sprintf(
                            '%s/%d_%s_%s_%s.webp',
                            $clanName,
                            $cardPosition,
                            'Phoenix',
                            $sideName,
                            $cardName
                        );
                    }
                }

                if ($card->getClan() === Clan::Neutral) {
                    $filename = sprintf(
                        '%s/%s_%s_%d_%s.webp',
                        $clanName,
                        $clanName,
                        $sideName,
                        $cardPosition,
                        $cardName
                    );
                }
            }

            if ($pack->getName() === 'Children of the Empire') {
                $filename = sprintf(
                    '%s_%s_%d_%s.webp',
                    $clanName,
                    $sideName,
                    $cardPosition,
                    $cardName
                );
            }

            $filename = match ($card->getId()) {
                '39-the-western-wind' => '85_Unicorn_The Western Wind.webp',
                '41-ikoma-ujiaki' => '117_Lion_C_Ikoma Ujiaki.webp', // side
                '33-ceaseless-duty' => '107_Crane_C_Ceaseless Duty.webp',
                '24-mantra-of-air' => '105_Dragon_C_Mantra of Air.webp',
                '07-miya-satoshi' => '112_Neutral_C_Miya Satoshi.webp',
                '02-kitsuki-yaruma' => '6_Dragone_D_Kitsuki Yaruma.webp',
                '27-support-of-the-lion' => 'Lion/Spescial_3_Lion_Support Of The Lion.webp',
                '27-yasuki-hikaru' => 'Lion/Lion_D_14_Yasuki Hikaru.webp', // clan
                '17-kakita-ryoku' => 'Unicorn/Crain_D_13_Kakita Ryoku.webp',
                '17-shiro-shinjo' => 'Unicorn/Unicorn_Stronghold.webp',
                '17-support-of-the-unicorn' => 'Unicorn/Spescial_3_Unicorn_Support Of The Unicorn.webp',
                '08-support-of-the-phoenix' => 'Pheonix/Spescial_3_Phoenix_Support Of The Phoenix.webp',
                '25-support-of-the-dragon' => 'Dragon/Spescial_3_Dragon_Support Of The Dragon.webp',
                '18-support-of-the-crane' => 'Crane/Spescial_3_Crane_Support Of The Crane.webp',
                '15-support-of-the-scorpion' => 'Scorpion/Spescial_3_Scorpion_Support Of The Scorpion.webp',
                '18-armament-artisan' => 'Crane/Neutral_D_12_Arnament Artisan.webp', // position
                '26-support-of-the-crab' => 'Crab/Spescial_3_Crab_Support Of The Crab.webp',
                '26-reinforced-plate' => 'Crab/Crab_C_35_Reinforced Plate.webp', // position
                '26-shinjo-yasamura' => 'Crab/Crab_D_22_Shinjo Yasamura.webp', // clan
                '26-daidoji-netsu' => 'Crab/Crain_D_18_Daidoji Netsu.webp',
                '09-hurricane-punch' => '17_Dagon_C_Hurricane Punch.webp',
                '14-being-and-becoming' => '115_Dragon_D_Being and Becoming.webp', // side
                '14-magistrate-station' => '102_Crane_ Province_Magistrate Station.webp',
                '12-hawk-tattoo' => '75_Dragon_D_Hawk Tattoo.webp', // side
                '10-adopted-kin' => '33_Dragon_D_Adopted Kin.webp', // side
                '11-smoke' => '54_Dragon_D_Smoke.webp', // side
                '09-ancient-master' => '16_Dragon_D_Ancient Master.webp', // side
                '13-void-fist' => '94_Dragon_D_Void Fist.webp', // side
                '10-kuni-yori' => '25_D_Crab_Kuni Yori.webp',
                '14-mantra-of-earth' => '116_Dragon_D_Mantra of Earth.webp', // side
                '01-keeper-of-water' => 'newRole Card_Side A_217_Keeper Of Water.webp',
                '01-seeker-of-water' => 'newRole Card_Side B_217_Keeper Of Water.webp',
                '01-seeker-of-earth' => 'newRole Card_Side B_215_Keeper Of Earth.webp',
                '01-magnificent-kimono' => 'Pheonix/172_Phoenix_Event_C_Magnifecent Kimono.webp', // type
                '01-keeper-of-void' => 'newRole Card_Side A_218_Keeper Of Void.webp',
                '01-seeker-of-void' => 'newRole Card_Side B_218_Keeper Of Void.webp',
                '01-keeper-of-air' => 'newRole Card_Side A_214_Keeper Of Air.webp',
                '01-seeker-of-air' => 'newRole Card_Side B_214_Keeper Of Air.webp',
                '01-keeper-of-earth' => 'newRole Card_Side A_215_Keeper Of Earth.webp',
                '01-the-art-of-peace' => 'Crane/Crane _Province_9_The Art Of Peace.webp',
                '01-seeker-of-fire' => 'newRole Card_Side B_216_Keeper Of Fire.webp',
                '01-keeper-of-fire' => 'newRole Card_Side A_216_Keeper Of Fire.webp',
                '16-iaijutsu-master' => 'Crab_C_53_Iaijutsu Master.webp', // clan
                default => $filename,
            };

            $expectedPath = sprintf(
                '%s/%s/%s',
                $path,
                $directoryName,
                $filename
            );

            $dictionary[strtolower($expectedPath)] = $card->getId();
        }

        $finder = new Finder();
        $finder->files()->in($path)->name('*.webp');

        foreach ($finder as $file) {
            $absoluteFilePath = $file->getRealPath();
            if (str_contains($absoluteFilePath, 'Clan Wars')
                || str_contains($absoluteFilePath, 'Under Fu Leng\'s Shadow')) {
                continue;
            }
            if (!isset($dictionary[strtolower($absoluteFilePath)])) {
                $io->error($absoluteFilePath);
                continue;
            }

            $fs->copy(
                $absoluteFilePath,
                sprintf('%s/cdn/cards/%s.webp', $this->projectDir, $dictionary[strtolower($absoluteFilePath)]),
                true,
            );
            $fs->remove($absoluteFilePath);
            $io->success('Moved ' . $absoluteFilePath);
        }

        return Command::SUCCESS;
    }
}

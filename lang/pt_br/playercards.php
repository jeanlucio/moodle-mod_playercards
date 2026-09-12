<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Portuguese (Brazil) strings for PlayerCards.
 *
 * @package    mod_playercards
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['activatelore'] = 'Ativar';
$string['addlorecard'] = 'Adicionar carta Lore';
$string['addquestion'] = 'Adicionar pergunta';
$string['aicorrectresult'] = 'A IA respondeu corretamente e ganhou {$a} pontos de vida.';
$string['aidifficultydefault'] = 'Dificuldade padrão da IA';
$string['aidifficultydefault_help'] = 'Dificuldade que o estudante pode escolher ao iniciar uma partida contra a IA (fácil, normal ou difícil). Define a opção pré-selecionada por padrão.';
$string['aieventattack'] = 'A IA atacou seu {$a->target} com {$a->attacker}.';
$string['aieventattackdirect'] = 'A IA atacou diretamente com {$a->attacker}, causando {$a->damage} de dano.';
$string['aieventdamagedealt'] = 'Causou {$a} de dano.';
$string['aieventmuster'] = 'A IA convocou {$a}.';
$string['aieventtargetdestroyed'] = 'Seu Guardião foi destruído.';
$string['aiturn'] = 'Vez da IA';
$string['answerlines'] = 'Opções de resposta';
$string['answerlines_help'] = 'Uma opção por linha. Marque a correta com um asterisco (*) no início da linha. Ignorado quando o tipo de pergunta é Descrição.';
$string['approve'] = 'Aprovar';
$string['attackposture'] = 'Postura ofensiva';
$string['attemptsreport'] = 'Relatório de partidas do PlayerCards: {$a}';
$string['attemptsreportnav'] = 'Relatório de partidas';
$string['boostatk'] = 'Reforçar ATK';
$string['boostdef'] = 'Reforçar DEF';
$string['changeposturebtn'] = 'Trocar postura';
$string['classpromotion'] = 'Promoção de Classe';
$string['cointossai'] = 'A IA venceu o cara ou coroa e começa jogando.';
$string['cointossyou'] = 'Você venceu o cara ou coroa e começa jogando.';
$string['completionwins_desc'] = 'Vencer a partida pelo menos {$a} vez(es)';
$string['completionwinsgroup'] = 'Estudante precisa vencer pelo menos';
$string['confirmdeletelore'] = 'Excluir esta carta Lore? Essa ação não pode ser desfeita.';
$string['confirmdeletequestion'] = 'Excluir esta pergunta? Essa ação não pode ser desfeita.';
$string['contentheader'] = 'Fonte de conteúdo Lore';
$string['deckerrorinfoquizfloor'] = 'O deck precisa de pelo menos {$a} cartas Info/Quiz combinadas.';
$string['deckerrorlevelfloor'] = 'O deck precisa de pelo menos {$a->min} Guardião(ões) de nível {$a->level}.';
$string['deckerrormaxcopies'] = 'Esta carta permite no máximo {$a} cópias por deck.';
$string['deckerrorsize'] = 'O deck precisa ter entre {$a->min} e {$a->max} cartas (atualmente {$a->actual}).';
$string['declareattack'] = 'Selecione um Guardião inimigo para atacar';
$string['defenseposture'] = 'Postura defensiva';
$string['difficulty_easy'] = 'Fácil';
$string['difficulty_hard'] = 'Difícil';
$string['difficulty_medium'] = 'Médio';
$string['difficulty_normal'] = 'Normal';
$string['directattackbtn'] = 'Ataque direto';
$string['effectapplied'] = 'Efeito aplicado.';
$string['emptyguardianslot'] = 'Slot de Guardião vazio';
$string['emptyloreslot'] = 'Slot de Lore vazio';
$string['endturnbtn'] = 'Encerrar turno';
$string['error_alreadyattacked'] = 'Este Guardião já atacou neste turno.';
$string['error_atleastonesource'] = 'Selecione pelo menos uma fonte de conteúdo.';
$string['error_banksourcenotready'] = 'Esta carta usa o banco de questões do Moodle como fonte de conteúdo, ainda não suportado — por enquanto só o pool próprio funciona.';
$string['error_completionwins'] = 'Informe um número de vitórias maior que zero.';
$string['error_emptyslot'] = 'Esse slot de campo não tem nenhum Guardião.';
$string['error_hud_cost_qty'] = 'Informe uma quantidade de pelo menos 1 quando um item do PlayerHUD estiver configurado.';
$string['error_invaliddifficulty'] = 'Dificuldade de IA inválida.';
$string['error_invalideffecttarget'] = 'Escolha um Guardião válido para este efeito atingir.';
$string['error_invalidhandcard'] = 'Essa carta não é um Guardião na sua mão.';
$string['error_invalidmatchtoken'] = 'Esta partida não está mais ativa.';
$string['error_invalidposture'] = 'Postura inválida.';
$string['error_invalidsacrifice'] = 'Escolha um Guardião de nível 1-3 seu já em campo para sacrificar.';
$string['error_invalidtarget'] = 'Esse slot de campo não tem Guardião para atacar.';
$string['error_matchfinished'] = 'Esta partida já terminou.';
$string['error_maxturns'] = 'O limite de turnos não pode ser negativo.';
$string['error_mustbeattackposture'] = 'Só um Guardião em Postura Ofensiva pode declarar ataque.';
$string['error_musteralreadyused'] = 'Você já convocou um Guardião neste turno.';
$string['error_musttargetguardian'] = 'O oponente tem um Guardião em campo — ataque-o em vez de atacar diretamente.';
$string['error_needfieldsacrifice'] = 'Pelo menos um dos dois sacrifícios precisa já estar em campo, não os dois da mão.';
$string['error_needonecorrect'] = 'Exatamente uma opção de resposta precisa estar marcada como correta com um asterisco (*) no início.';
$string['error_needtwoanswers'] = 'Informe pelo menos duas opções de resposta.';
$string['error_needtwosacrifices'] = 'A Promoção de Classe precisa de exatamente 2 Guardiões para sacrificar.';
$string['error_noactivedeck'] = 'Você precisa de um deck ativo antes de iniciar uma partida.';
$string['error_nobattlephaseturn1'] = 'Quem começa a partida não tem Fase de Batalha no turno 1.';
$string['error_nocontentavailable'] = 'Esta carta ainda não tem conteúdo aprovado disponível.';
$string['error_notmulliganphase'] = 'A decisão de mulligan não está disponível agora.';
$string['error_notquizcard'] = 'Essa carta não é do tipo Quiz.';
$string['error_notyourturn'] = 'Não é a sua vez.';
$string['error_posturealreadyused'] = 'Você já trocou a postura de um Guardião neste turno.';
$string['error_promotionnotauthorized'] = 'Nenhuma Promoção de Classe está autorizada no momento — ative uma carta Lore que a habilite primeiro.';
$string['error_promotiontrap'] = 'Uma carta Armadilha não pode autorizar uma Promoção de Classe — use Info ou Quiz.';
$string['error_quiztimerseconds'] = 'O cronômetro do Quiz precisa ser de pelo menos 5 segundos.';
$string['error_required'] = 'Este campo é obrigatório.';
$string['error_sacrificenotallowed'] = 'Um Guardião de nível 1-3 é convocado de graça — não precisa de sacrifício.';
$string['error_sacrificerequired'] = 'Convocar um Guardião de nível 4-5 exige sacrificar um Guardião de nível 1-3 em campo.';
$string['error_slotoccupied'] = 'Esse slot de campo já está ocupado.';
$string['error_summoningsickness'] = 'Um Guardião não pode trocar de postura no turno em que foi convocado.';
$string['error_unknowneffecttype'] = 'Esta carta tem um tipo de efeito que o jogo não reconhece.';
$string['error_useactivatequiz'] = 'Use a ação de ativação de Quiz para esta carta.';
$string['grademethod'] = 'Método de avaliação';
$string['grademethod_average'] = 'Média de todas as partidas';
$string['grademethod_first'] = 'Primeira partida';
$string['grademethod_highest'] = 'Melhor partida';
$string['grademethod_last'] = 'Última partida';
$string['guardian'] = 'Guardião';
$string['guardianslots'] = 'Slots de Guardiões';
$string['howtoplay'] = 'Como jogar';
$string['howtoplaybody'] = 'PlayerCards é um jogo de cartas colecionável. Convoque Guardiões para batalhar e ative cartas Lore — Info, Quiz e Armadilha — para revisar o conteúdo da disciplina e virar o jogo a seu favor.';
$string['hud_card_cost_item'] = 'Item do PlayerHUD usado como moeda para comprar cartas';
$string['hud_card_cost_item_help'] = 'ID do item do block_playerhud usado para cobrar dos estudantes na compra de cartas na loja. Deixe 0 para desativar as compras. Um seletor de item de verdade chega numa fase de desenvolvimento posterior.';
$string['hud_card_cost_qty'] = 'Custo base por carta';
$string['hud_retry_cost_item'] = 'Item do PlayerHUD cobrado por uma nova tentativa de partida';
$string['hud_retry_cost_item_help'] = 'ID do item do block_playerhud cobrado quando um estudante inicia uma nova partida. Deixe 0 para tentativas gratuitas.';
$string['hud_retry_cost_qty'] = 'Quantidade cobrada por tentativa';
$string['hudheader'] = 'Integração com o PlayerHUD';
$string['keephand'] = 'Manter mão';
$string['lifepoints'] = 'Pontos de vida';
$string['lore'] = 'Lore';
$string['lorecontent'] = 'Conteúdo';
$string['lorecontent_help'] = 'Texto educacional (Info) ou descrição do efeito (Quiz/Armadilha).';
$string['loredeleted'] = 'Carta Lore excluída.';
$string['loredifficulty'] = 'Dificuldade';
$string['loreeffecttype'] = 'Tipo de efeito';
$string['loreeffecttype_help'] = 'Um identificador curto do efeito que esta carta aplica (ex.: atk_buff, def_buff, lp_heal, lp_damage, destroy, enable_promotion). SCOPE.md 4.6.';
$string['loreeffectvalue'] = 'Valor do efeito';
$string['loreinfo'] = 'Info';
$string['loremaxcopies'] = 'Máximo de cópias por deck';
$string['lorename'] = 'Nome da carta';
$string['lorequestioncategory'] = 'Categoria de conteúdo';
$string['lorequestioncategory_help'] = 'Rótulo de categoria (banco próprio) ou ID de categoria do banco de questões (banco real) de onde esta carta sorteia conteúdo a cada ativação. SCOPE.md 4.6.';
$string['lorequestionsource'] = 'Fonte de conteúdo';
$string['lorequiz'] = 'Quiz';
$string['loresaved'] = 'Carta Lore salva.';
$string['loreslots'] = 'Slots de Lore';
$string['loretrap'] = 'Armadilha';
$string['managelore'] = 'Gerenciar cartas Lore e perguntas: {$a}';
$string['managelorenav'] = 'Gerenciar cartas Lore e perguntas';
$string['matchendedlabel'] = 'Partida encerrada: {$a}';
$string['matchheader'] = 'Configurações da partida';
$string['maxturns'] = 'Limite de turnos';
$string['maxturns_help'] = 'Número máximo de turnos individuais antes da partida encerrar automaticamente, dando a vitória a quem tiver mais pontos de vida. 0 significa sem limite.';
$string['modulename'] = 'PlayerCards: Guardiões & Lore';
$string['modulename_help'] = 'A atividade PlayerCards permite ao professor criar um jogo de cartas colecionável no qual o conteúdo da disciplina vira cartas Info e Quiz jogáveis, enquanto os estudantes duelam contra uma inteligência artificial.';
$string['modulenameplural'] = 'Atividades PlayerCards';
$string['musterguardian'] = 'Convocar';
$string['noattemptsyet'] = 'Nenhuma partida concluída ainda.';
$string['opponenthand'] = 'Mão do oponente';
$string['playagain'] = 'Jogar novamente';
$string['playercards:addinstance'] = 'Adicionar uma nova atividade PlayerCards';
$string['playercards:managelore'] = 'Gerenciar cartas Lore e perguntas do PlayerCards';
$string['playercards:view'] = 'Jogar PlayerCards';
$string['playercards:viewreports'] = 'Ver relatórios do PlayerCards';
$string['pluginadministration'] = 'Administração do PlayerCards';
$string['pluginname'] = 'PlayerCards: Guardiões & Lore';
$string['promotionavailable'] = 'Promoção de Classe disponível (+{$a})';
$string['qtype_description'] = 'Descrição (Info, sem resposta)';
$string['qtype_multichoice'] = 'Múltipla escolha';
$string['qtype_truefalse'] = 'Verdadeiro/Falso';
$string['questionapproved'] = 'Aprovada';
$string['questioncategorylabel'] = 'Categoria';
$string['questioncategorylabel_help'] = 'Rótulo livre que agrupa itens sorteáveis pela mesma carta Lore. SCOPE.md 4.6.';
$string['questiondeleted'] = 'Pergunta excluída.';
$string['questionpool'] = 'Pool de perguntas próprio do PlayerCards';
$string['questionsaved'] = 'Pergunta salva.';
$string['questionsource_bank'] = 'Banco de questões real do Moodle';
$string['questionsource_own'] = 'Pool de perguntas próprio do PlayerCards';
$string['questiontextlabel'] = 'Texto da pergunta / conteúdo';
$string['questiontype'] = 'Tipo';
$string['quiztimerseconds'] = 'Cronômetro de resposta do Quiz (segundos)';
$string['quiztimerseconds_help'] = 'Quanto tempo o oponente tem para responder uma carta Quiz depois de ativada. Recomendado entre 15 e 30 segundos.';
$string['redrawhand'] = 'Comprar mão nova';
$string['result'] = 'Resultado';
$string['resultloss'] = 'Derrota';
$string['resultwin'] = 'Vitória';
$string['sacrificialmuster'] = 'Convocação por sacrifício';
$string['selectdifficulty'] = 'Dificuldade';
$string['selecteffecttarget'] = 'Selecione um alvo para este efeito';
$string['selectemptyloreslot'] = 'Selecione um slot de Lore vazio';
$string['selectpromotionsacrifice'] = 'Selecione 2 Guardiões seus para sacrificar (pelo menos um já em campo)';
$string['selectpromotiontarget'] = 'Selecione um Guardião para receber o bônus';
$string['selectsacrifice'] = 'Selecione um Guardião de nível 1-3 seu em campo para sacrificar';
$string['selectslottomuster'] = 'Selecione um slot vazio para convocar este Guardião';
$string['startmatch'] = 'Iniciar partida';
$string['summonedthisturn'] = 'Convocado neste turno';
$string['turnnumberlabel'] = 'Turno {$a}';
$string['turnsplayed'] = 'Turnos jogados';
$string['unapprove'] = 'Remover aprovação';
$string['wrongansweractivated'] = 'Resposta errada — efeito ativado';
$string['yourhand'] = 'Sua mão';
$string['yourturn'] = 'Sua vez';

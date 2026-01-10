export type PoolStatus = 'selection' | 'upcoming' | 'active' | 'finished';

export type PlayerPosition = 'C' | 'AG' | 'AD' | 'D' | 'G';

export interface Player {
    id?: string;
    team: string;
    name: string;
    position: PlayerPosition;
    goals: number;
    assists: number;
    shutouts: number;
    victories: number;
    defeats: number;
    points: number;
}

export interface Pool {
    id: number;
    name: string;
    status: PoolStatus;
    participants_count: number;
    rule_setting: string;
    start_date: string;
    end_date: string;
    players?: Player[];
}

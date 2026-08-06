<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Configuration\Models\State;

class StateSeeder extends Seeder
{
    /**
     * Indian GST states and union territories.
     */
    protected array $states = [
        ['state_code' => '01', 'state_name' => 'Jammu and Kashmir', 'type' => 'Union Territory'],
        ['state_code' => '02', 'state_name' => 'Himachal Pradesh', 'type' => 'State'],
        ['state_code' => '03', 'state_name' => 'Punjab', 'type' => 'State'],
        ['state_code' => '04', 'state_name' => 'Chandigarh', 'type' => 'Union Territory'],
        ['state_code' => '05', 'state_name' => 'Uttarakhand', 'type' => 'State'],
        ['state_code' => '06', 'state_name' => 'Haryana', 'type' => 'State'],
        ['state_code' => '07', 'state_name' => 'Delhi', 'type' => 'Union Territory'],
        ['state_code' => '08', 'state_name' => 'Rajasthan', 'type' => 'State'],
        ['state_code' => '09', 'state_name' => 'Uttar Pradesh', 'type' => 'State'],
        ['state_code' => '10', 'state_name' => 'Bihar', 'type' => 'State'],
        ['state_code' => '11', 'state_name' => 'Sikkim', 'type' => 'State'],
        ['state_code' => '12', 'state_name' => 'Arunachal Pradesh', 'type' => 'State'],
        ['state_code' => '13', 'state_name' => 'Nagaland', 'type' => 'State'],
        ['state_code' => '14', 'state_name' => 'Manipur', 'type' => 'State'],
        ['state_code' => '15', 'state_name' => 'Mizoram', 'type' => 'State'],
        ['state_code' => '16', 'state_name' => 'Tripura', 'type' => 'State'],
        ['state_code' => '17', 'state_name' => 'Meghalaya', 'type' => 'State'],
        ['state_code' => '18', 'state_name' => 'Assam', 'type' => 'State'],
        ['state_code' => '19', 'state_name' => 'West Bengal', 'type' => 'State'],
        ['state_code' => '20', 'state_name' => 'Jharkhand', 'type' => 'State'],
        ['state_code' => '21', 'state_name' => 'Odisha', 'type' => 'State'],
        ['state_code' => '22', 'state_name' => 'Chhattisgarh', 'type' => 'State'],
        ['state_code' => '23', 'state_name' => 'Madhya Pradesh', 'type' => 'State'],
        ['state_code' => '24', 'state_name' => 'Gujarat', 'type' => 'State'],
        ['state_code' => '26', 'state_name' => 'Dadra and Nagar Haveli and Daman and Diu', 'type' => 'Union Territory'],
        ['state_code' => '27', 'state_name' => 'Maharashtra', 'type' => 'State'],
        ['state_code' => '28', 'state_name' => 'Andhra Pradesh', 'type' => 'State'],
        ['state_code' => '29', 'state_name' => 'Karnataka', 'type' => 'State'],
        ['state_code' => '30', 'state_name' => 'Goa', 'type' => 'State'],
        ['state_code' => '31', 'state_name' => 'Lakshadweep', 'type' => 'Union Territory'],
        ['state_code' => '32', 'state_name' => 'Kerala', 'type' => 'State'],
        ['state_code' => '33', 'state_name' => 'Tamil Nadu', 'type' => 'State'],
        ['state_code' => '34', 'state_name' => 'Puducherry', 'type' => 'Union Territory'],
        ['state_code' => '35', 'state_name' => 'Andaman and Nicobar Islands', 'type' => 'Union Territory'],
        ['state_code' => '36', 'state_name' => 'Telangana', 'type' => 'State'],
        ['state_code' => '37', 'state_name' => 'Andhra Pradesh (New)', 'type' => 'State'],
        ['state_code' => '38', 'state_name' => 'Ladakh', 'type' => 'Union Territory'],
    ];

    /**
     * Seed the GST states into the states table.
     */
    public function run(): void
    {
        foreach ($this->states as $state) {
            State::updateOrCreate(
                ['state_code' => $state['state_code']],
                [
                    'state_name' => $state['state_name'],
                    'type' => $state['type'] ?? null,
                ]
            );
        }

        $this->command->info('GST states seeded successfully. Total: ' . count($this->states));
    }
}

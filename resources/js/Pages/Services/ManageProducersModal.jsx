import React, { useState } from 'react';
import { Input } from '@/Components/ui/input';
import { Button } from '@/Components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '@/Components/ui/dialog';

export default function ManageProducersModal({ 
    service, 
    availableUsers, 
    closeModal, 
    showModal, 
    handleAttachUser, 
    handleDetachUser, 
    processing 
}) {
    const [searchAssigned, setSearchAssigned] = useState('');
    const [searchUnassigned, setSearchUnassigned] = useState('');

    const filteredAssignedProducers = service.users.filter(user =>
        (user.name || '').toLowerCase().includes(searchAssigned.toLowerCase()) ||
        (user.email || '').toLowerCase().includes(searchAssigned.toLowerCase())
    );

    const filteredUnassignedProducers = availableUsers.data.filter(user =>
        !service.users.some(assignedUser => assignedUser.id === user.id) &&
        ((user.name || '').toLowerCase().includes(searchUnassigned.toLowerCase()) ||
        (user.email || '').toLowerCase().includes(searchUnassigned.toLowerCase()))
    );

    return (
        <Dialog open={showModal} onOpenChange={closeModal}>
            <DialogContent className="max-w-4xl h-[80vh] flex flex-col">
                <DialogHeader>
                    <DialogTitle>Administrar Productores para {service.name}</DialogTitle>
                    <DialogDescription>
                        {service.description}
                    </DialogDescription>
                </DialogHeader>

                <div className="flex-grow grid grid-cols-1 md:grid-cols-2 gap-4 overflow-y-auto p-4">
                    {/* Unassigned Producers Column */}
                    <div>
                        <h4 className="text-md font-medium text-gray-900 mb-2">Productores Disponibles</h4>
                        <Input
                            type="text"
                            placeholder="Buscar productores..."
                            value={searchUnassigned}
                            onChange={(e) => setSearchUnassigned(e.target.value)}
                            className="mb-4"
                        />
                        <ul className="bg-gray-100 p-2 rounded-md min-h-[200px] h-full overflow-y-auto">
                            {filteredUnassignedProducers.length > 0 ? (
                                filteredUnassignedProducers.map((user) => (
                                    <li key={user.id} className="bg-white p-2 mb-2 rounded-md shadow-sm flex justify-between items-center">
                                        <span>{user.name} ({user.email})</span>
                                        <Button onClick={() => handleAttachUser(user.id)} size="sm" disabled={processing}>
                                            Asociar
                                        </Button>
                                    </li>
                                ))
                            ) : (
                                <p className="text-sm text-gray-600">No hay productores disponibles.</p>
                            )}
                        </ul>
                    </div>

                    {/* Assigned Producers Column */}
                    <div>
                        <h4 className="text-md font-medium text-gray-900 mb-2">Productores Asociados</h4>
                        <Input
                            type="text"
                            placeholder="Buscar productores..."
                            value={searchAssigned}
                            onChange={(e) => setSearchAssigned(e.target.value)}
                            className="mb-4"
                        />
                        <ul className="bg-blue-100 p-2 rounded-md min-h-[200px] h-full overflow-y-auto">
                            {filteredAssignedProducers.length > 0 ? (
                                filteredAssignedProducers.map((user) => (
                                    <li key={user.id} className="bg-white p-2 mb-2 rounded-md shadow-sm flex justify-between items-center">
                                        <span>{user.name} ({user.email})</span>
                                        <Button onClick={() => handleDetachUser(user.id)} variant="destructive" size="sm" disabled={processing}>
                                            Quitar
                                        </Button>
                                    </li>
                                ))
                            ) : (
                                <p className="text-sm text-gray-600">No hay productores asociados.</p>
                            )}
                        </ul>
                    </div>
                </div>

                <DialogFooter>
                    <Button type="button" onClick={closeModal} disabled={processing}>
                        Cerrar
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}